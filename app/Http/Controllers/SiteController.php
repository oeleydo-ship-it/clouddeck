<?php

namespace App\Http\Controllers;

use App\Actions\Deployments\StartDeployment;
use App\Actions\Deployments\StartRollback;
use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Http\Requests\StoreSiteRequest;
use App\Jobs\Sites\CheckSiteQueueHealthJob;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Jobs\Sites\DeleteSiteJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Services\AuditLogger;
use App\Services\EnvironmentFile;
use App\Services\QuotaManager;
use App\Services\WordPressConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        return view('sites.index', ['sites' => $request->user()->sites()->with(['server', 'latestDeployment'])->latest()->paginate(15)]);
    }

    public function create(Request $request): View
    {
        return view('sites.create', ['servers' => $request->user()->accessibleServers()->where('status', ServerStatus::Ready)->get()]);
    }

    public function store(StoreSiteRequest $request, QuotaManager $quotas): RedirectResponse
    {
        $quotas->assertCanCreate($request->user(), 'sites');
        $site = DB::transaction(function () use ($request) {
            $site = $request->user()->sites()->create([...$request->validated(), 'auto_deploy' => $request->boolean('auto_deploy'), 'zero_downtime' => $request->boolean('zero_downtime', true), 'webhook_secret' => Str::random(64), 'status' => 'configuring']);

            // A WordPress install is configured by a generated wp-config.php, not by a
            // Laravel environment file, so seeding APP_KEY and a queue connection into it
            // would leave keys nothing ever reads.
            if ($site->isWordPress()) {
                app(WordPressConfig::class)->ensureSalts($site);

                return $site;
            }

            foreach (['APP_NAME' => $site->domain, 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://'.$site->domain, 'APP_KEY' => '', 'LOG_CHANNEL' => 'stack', 'CACHE_STORE' => 'redis', 'QUEUE_CONNECTION' => 'redis', 'SESSION_DRIVER' => 'redis', 'REDIS_HOST' => '127.0.0.1'] as $key => $value) {
                $site->environmentVariables()->create(['key' => $key, 'value' => $value, 'is_secret' => in_array($key, ['APP_KEY'], true)]);
            }

            return $site;
        });
        ConfigureSiteJob::dispatch($site->id)->onQueue('provisioning');

        return redirect()->route('sites.show', $site)->with('status', 'Site configuration has been queued.');
    }

    public function show(Request $request, Site $site, EnvironmentFile $environment): View
    {
        $this->authorize('view', $site);

        return view('sites.show', ['site' => $site->load(['server', 'environmentVariables', 'queueWorkers', 'sslCertificates', 'cronJobs']), 'deployments' => $site->deployments()->with('user')->latest()->paginate(20), 'environment' => $environment->render($site->environmentVariables), 'rollbackReleases' => $site->deployments()->where('status', DeploymentStatus::Successful)->whereNotNull('release')->latest('finished_at')->limit(5)->pluck('release')->all()]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate(['repository_url' => ['required', 'string', 'max:2048', 'regex:/^(https:\/\/[^\s]+|git@[^\s:]+:[^\s]+)$/'], 'branch' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/'], 'php_version' => ['required', Rule::in(['8.2', '8.3', '8.4'])], 'deployment_script' => ['nullable', 'string', 'max:30000'], 'auto_deploy' => ['sometimes', 'boolean'], 'zero_downtime' => ['sometimes', 'boolean']]);
        $site->update([...$data, 'auto_deploy' => $request->boolean('auto_deploy'), 'zero_downtime' => $request->boolean('zero_downtime')]);

        return back()->with('status', 'Deployment settings updated.');
    }

    public function environment(Request $request, Site $site, EnvironmentFile $environment): RedirectResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate(['environment' => ['present', 'string', 'max:65535']]);
        $variables = $environment->parse($data['environment']);
        DB::transaction(function () use ($site, $variables) {
            $site->environmentVariables()->whereNotIn('key', array_keys($variables))->delete();
            foreach ($variables as $key => $value) {
                $site->environmentVariables()->updateOrCreate(['key' => $key], ['value' => $value, 'is_secret' => preg_match('/(KEY|SECRET|TOKEN|PASSWORD|PRIVATE)/', $key) === 1]);
            }
        });

        return back()->with('status', 'Environment variables encrypted and saved.');
    }

    public function deploy(Request $request, Site $site, StartDeployment $start): RedirectResponse
    {
        $this->authorize('deploy', $site);
        if ($site->status !== 'active' || $site->server->status !== ServerStatus::Ready) {
            return back()->withErrors(['deployment' => 'The site and server must be active before deployment.']);
        }
        if ($site->deployments()->whereIn('status', [DeploymentStatus::Pending, DeploymentStatus::Running])->exists()) {
            return back()->withErrors(['deployment' => 'A deployment is already in progress.']);
        }
        $deployment = $start->execute($site, $request->user());

        return redirect()->route('deployments.show', $deployment)->with('status', 'Deployment queued.');
    }

    public function rollback(Request $request, Site $site, Deployment $deployment, StartRollback $rollback): RedirectResponse
    {
        $this->authorize('deploy', $site);
        abort_unless($deployment->site_id === $site->id && $deployment->release && in_array($deployment->status, [DeploymentStatus::Successful, DeploymentStatus::RolledBack], true), 404);
        abort_unless(in_array($deployment->release, $site->deployments()->where('status', DeploymentStatus::Successful)->whereNotNull('release')->latest('finished_at')->limit(5)->pluck('release')->all(), true), 422, 'This release is no longer retained on the server.');
        if ($site->deployments()->whereIn('status', [DeploymentStatus::Pending, DeploymentStatus::Running])->exists()) {
            return back()->withErrors(['deployment' => 'A deployment is already in progress.']);
        }
        $created = $rollback->execute($deployment, $request->user());

        return redirect()->route('deployments.show', $created)->with('status', 'Rollback queued.');
    }

    public function destroy(Request $request, Site $site, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $site);
        $request->validate(['confirmation' => ['required', Rule::in([$site->domain])]]);
        if ($site->deployments()->whereIn('status', [DeploymentStatus::Pending, DeploymentStatus::Running])->exists()) {
            return back()->withErrors(['site' => 'Wait for the in-progress deployment to finish before deleting this site.']);
        }

        $audit->record($request, 'site.deleted', $site, ['domain' => $site->domain], []);
        $site->delete();
        DeleteSiteJob::dispatch($site->id)->onQueue('provisioning');

        return redirect()->route('sites.index')->with('status', 'Site removed. Its remote configuration and files are being cleaned up.');
    }

    public function queueHealth(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422, 'Deploy the site at least once before checking queue health.');
        CheckSiteQueueHealthJob::dispatch($site->id);

        return back()->with('status', 'Checking failed job count.');
    }
}
