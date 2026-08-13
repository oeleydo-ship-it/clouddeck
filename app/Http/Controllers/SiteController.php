<?php

namespace App\Http\Controllers;

use App\Actions\Deployments\StartDeployment;
use App\Actions\Deployments\StartRollback;
use App\Actions\Sites\CreateStagingSite;
use App\Actions\Sites\PromoteStagingSite;
use App\Enums\DeploymentStatus;
use App\Enums\ServerStatus;
use App\Http\Requests\StoreSiteRequest;
use App\Jobs\Sites\CheckSiteQueueHealthJob;
use App\Jobs\Sites\CheckWordPressInstallJob;
use App\Jobs\Sites\ConfigureSiteJob;
use App\Jobs\Sites\DeleteSiteJob;
use App\Jobs\Sites\RefreshWordPressInventoryJob;
use App\Jobs\Monitoring\CheckSiteDnsJob;
use App\Jobs\Monitoring\CheckSiteUptimeJob;
use App\Models\Deployment;
use App\Models\Site;
use App\Models\SiteMonitorIncident;
use App\Notifications\OperationalEventNotification;
use App\Rules\GitRepositoryUrl;
use App\Services\AuditLogger;
use App\Services\EnvironmentFile;
use App\Services\QuotaManager;
use App\Services\SystemSettings;
use App\Services\WordPressConfig;
use App\Services\WordPressDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        $sites = $request->user()->sites();

        return Inertia::render('Sites/Index', [
            'title' => 'Sites',
            'sites' => (clone $sites)->with(['server', 'latestDeployment', 'stagingSite'])->latest()->paginate(15),
            'stagingSitesEnabled' => app(SystemSettings::class)->stagingSitesEnabled(),
            // Counted over every site the user owns, not just the current page, so the
            // strip keeps meaning once the list paginates.
            'summary' => [
                'total' => (clone $sites)->count(),
                'active' => (clone $sites)->where('status', 'active')->count(),
                'deployments' => Deployment::whereIn('site_id', (clone $sites)->select('id'))->whereDate('created_at', today())->count(),
                'failed' => Deployment::whereIn('site_id', (clone $sites)->select('id'))->where('status', DeploymentStatus::Failed)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Sites/Create', [
            'title' => 'Create a site',
            'servers' => $request->user()->accessibleServers()->where('status', ServerStatus::Ready)->get(['id', 'name', 'public_ip']),
            'phpVersions' => config('clouddeck.php_versions'),
            'defaultPhpVersion' => config('clouddeck.default_php_version'),
        ]);
    }

    public function store(StoreSiteRequest $request, QuotaManager $quotas): RedirectResponse
    {
        $server = $request->user()->accessibleServers()->whereKey($request->validated('server_id'))->firstOrFail();
        $quotas->assertCanCreateSite($request->user(), $server);
        $site = DB::transaction(function () use ($request) {
            $site = $request->user()->sites()->create([
                ...$request->validated(),
                'auto_deploy' => $request->boolean('auto_deploy'),
                'zero_downtime' => $request->boolean('zero_downtime', true),
                'webhook_secret' => Str::random(64),
                'status' => 'configuring',
                'environment' => 'production',
            ]);

            // A WordPress install is configured by a generated wp-config.php, not by a
            // Laravel environment file, so seeding APP_KEY and a queue connection into it
            // would leave keys nothing ever reads. React SPAs only need VITE_* at build time.
            if ($site->isWordPress()) {
                app(WordPressConfig::class)->ensureSalts($site);

                return $site;
            }

            if ($site->isReact()) {
                foreach (['VITE_APP_URL' => 'https://'.$site->domain, 'NODE_ENV' => 'production'] as $key => $value) {
                    $site->environmentVariables()->create(['key' => $key, 'value' => $value, 'is_secret' => false]);
                }

                return $site;
            }

            foreach (['APP_NAME' => $site->domain, 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://'.$site->domain, 'APP_KEY' => '', 'LOG_CHANNEL' => 'stack', 'CACHE_STORE' => 'redis', 'QUEUE_CONNECTION' => 'redis', 'SESSION_DRIVER' => 'redis', 'REDIS_HOST' => '127.0.0.1'] as $key => $value) {
                $site->environmentVariables()->create(['key' => $key, 'value' => $value, 'is_secret' => in_array($key, ['APP_KEY'], true)]);
            }

            return $site;
        });
        ConfigureSiteJob::dispatch($site->id)->onQueue('provisioning');

        $request->user()->notify(new OperationalEventNotification(
            event: 'site_added',
            title: $site->domain.' was added to '.$site->server->name,
            body: 'The '.$site->platformLabel().' site is being configured on the server. It can be deployed once that finishes.',
            url: route('sites.show', $site),
            context: ['site_id' => $site->id, 'server_id' => $site->server_id],
        ));

        return redirect()->route('sites.show', $site)->with('status', 'Site configuration has been queued.');
    }

    public function show(Request $request, Site $site, EnvironmentFile $environment): Response
    {
        $this->authorize('view', $site);

        // Read on first arrival rather than leaving the operator to press a button for
        // something Uplary could simply have asked the server for.
        if ($site->wordpressIsInstalled() && ! $site->wordpress_inventory_at) {
            RefreshWordPressInventoryJob::dispatch($site->id)->onQueue('operations');
        }

        $databaseKey = $site->isWordPress() ? 'DB_DATABASE' : 'DB_CONNECTION';
        $hasDatabase = $site->environmentVariables->contains(fn ($variable) => $variable->key === $databaseKey);
        $wordpressInstalled = $site->wordpressIsInstalled();
        $tabs = $site->isWordPress()
            ? ['overview' => 'Overview', 'themes' => 'Themes', 'plugins' => 'Plugins', 'backups' => 'Backups', 'environment' => 'Environment', 'ssl' => 'SSL', 'cron' => 'Cron', 'logs' => 'Logs', 'monitoring' => 'Monitoring']
            : ($site->isReact()
                ? ['overview' => 'Overview', 'backups' => 'Backups', 'environment' => 'Environment', 'deploy' => 'Deployment settings', 'ssl' => 'SSL', 'webhook' => 'Webhook', 'logs' => 'Logs', 'monitoring' => 'Monitoring']
                : ['overview' => 'Overview', 'backups' => 'Backups', 'environment' => 'Environment', 'deploy' => 'Deployment settings', 'ssl' => 'SSL', 'cron' => 'Cron', 'queue' => 'Queue & Reverb', 'webhook' => 'Webhook', 'logs' => 'Logs', 'monitoring' => 'Monitoring']);

        return Inertia::render('Sites/Show', [
            'title' => ($site->isLaravel() ? 'Laravel · ' : '').$site->domain,
            'site' => $site->load([
                'server',
                'environmentVariables',
                'queueWorkers',
                'sslCertificates',
                'cronJobs',
                'backups.user',
                'stagingSite',
                'productionSite',
                'monitorIncidents' => fn ($query) => $query->limit(20),
                'logSnapshots' => fn ($query) => $query->latest()->limit(5),
            ]),
            'meta' => [
                'is_react' => $site->isReact(),
                'is_wordpress' => $site->isWordPress(),
                'is_laravel' => $site->isLaravel(),
                'uses_php' => $site->usesPhp(),
                'platform_label' => $site->platformLabel(),
                'is_staging' => $site->isStaging(),
                'is_production' => $site->isProduction(),
                'wordpress_installed' => $wordpressInstalled,
                'has_database' => $hasDatabase,
                'secure' => $site->sslCertificates->contains(fn ($certificate) => $certificate->status === 'active'),
                'php_versions' => config('clouddeck.php_versions'),
                'scheduler_command' => 'cd /var/www/'.$site->domain.'/current && php artisan schedule:run',
                'webhook_url' => route('webhooks.site', $site),
                'visit_url' => ($site->sslCertificates->contains(fn ($certificate) => $certificate->status === 'active') ? 'https://' : 'http://').$site->domain,
                'deploy_action' => $site->isWordPress() ? ($wordpressInstalled ? 'Reinstall WordPress' : 'Install WordPress') : 'Deploy now',
                'database_notice' => (! $hasDatabase && ! $site->isReact())
                    ? 'Create a database before '.($site->isWordPress() ? 'installing' : 'deploying')
                    : null,
                'visit_label' => 'Visit site',
                'wordpress_source' => $site->isWordPress() ? 'wordpress.org' : null,
                'not_deployed' => $site->isWordPress() && ! $site->last_deployed_at ? 'Not deployed yet' : null,
                'finish_wordpress' => $site->isWordPress() && $site->last_deployed_at && ! $wordpressInstalled ? 'Finish the WordPress install' : null,
                'wp_install_path' => $site->isWordPress() ? 'wp-admin/install.php' : null,
                'setup_complete' => $wordpressInstalled ? 'Setup complete' : null,
                'scheduler_label' => $site->isLaravel() ? 'Laravel scheduler' : null,
                'kept_on_deploy' => $site->isLaravel() ? 'Kept on every deploy' : null,
                'horizon_access' => $site->isLaravel() ? 'Horizon dashboard access' : null,
                'queue_failed_label' => $site->isLaravel() && $site->queue_failed_count !== null ? $site->queue_failed_count.' failed' : null,
                'horizon_status' => $site->isLaravel()
                    ? (data_get($site->installed_packages, 'laravel/horizon')
                        ? data_get($site->installed_packages, 'laravel/horizon').' installed'
                        : 'not detected')
                    : null,
                'reverb_status' => $site->isLaravel()
                    ? (data_get($site->installed_packages, 'laravel/reverb')
                        ? data_get($site->installed_packages, 'laravel/reverb').' installed'
                        : 'not detected')
                    : null,
                'wp_update_available' => $site->isWordPress() ? 'Update available' : null,
                'wp_active' => $site->isWordPress() ? 'Active' : null,
                'wp_last_read_failed' => $site->isWordPress() && $site->wordpress_inventory_error ? 'The last read failed' : null,
                'wp_browse_themes' => $site->isWordPress() ? 'Browse themes' : null,
                'wp_browse_plugins' => $site->isWordPress() ? 'Browse plugins' : null,
                'wp_install_activate' => $site->isWordPress() ? 'Install and activate' : null,
                'wp_backup_now' => $site->isWordPress() ? 'Back up now' : null,
                'wp_installed_themes' => $site->isWordPress() ? 'Installed themes' : null,
                'wp_installed_plugins' => $site->isWordPress() ? 'Installed plugins' : null,
                'wp_directory_error' => $site->isWordPress() ? 'could not be reached' : null,
                'full_backup_label' => $site->isLaravel() || $site->isReact() ? 'Create full backup' : null,
                'full_backups_heading' => $site->isLaravel() || $site->isReact() ? 'Full site backups' : null,
            ],
            'tabs' => $tabs,
            'logSources' => $site->isReact()
                ? array_intersect_key(\App\Http\Controllers\LogController::SOURCES, array_flip(['nginx', 'nginx-access']))
                : ($site->isWordPress()
                    ? array_intersect_key(\App\Http\Controllers\LogController::SOURCES, array_flip(['nginx', 'nginx-access', 'php']))
                    : \App\Http\Controllers\LogController::SOURCES),
            'stagingSitesEnabled' => app(SystemSettings::class)->stagingSitesEnabled(),
            // Only fetched for the platform that can use it, and cached, so the directory
            // being slow or down never holds up a Laravel site's page.
            'directoryThemes' => $site->isWordPress() ? app(WordPressDirectory::class)->themes($request->query('theme_search')) : [],
            'directoryPlugins' => $site->isWordPress() ? app(WordPressDirectory::class)->plugins($request->query('plugin_search')) : [],
            'wordpressThemes' => $site->isWordPress() ? $site->wordpressInventory('theme') : [],
            'wordpressPlugins' => $site->isWordPress() ? $site->wordpressInventory('plugin') : [],
            'deployments' => $site->deployments()->with('user')->latest()->paginate(20),
            'environment' => $environment->render($site->environmentVariables),
            'rollbackReleases' => $site->deployments()->where('status', DeploymentStatus::Successful)->whereNotNull('release')->latest('finished_at')->limit(5)->pluck('release')->all(),
        ]);
    }

    public function storeStaging(Request $request, Site $site, CreateStagingSite $create): RedirectResponse
    {
        $this->authorize('update', $site);

        $data = $request->validate([
            'domain' => ['required', 'lowercase', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
            'branch' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/'],
        ]);

        $staging = $create->execute($site, $data);
        $ip = $site->server->public_ip;
        $status = filled($ip)
            ? 'Staging queued. Point an A record for '.$staging->domain.' at '.$ip.', then install SSL.'
            : 'Staging site configuration has been queued.';

        return redirect()->route('sites.show', $staging)->with('status', $status);
    }

    public function promote(Request $request, Site $site, PromoteStagingSite $promote): RedirectResponse
    {
        $this->authorize('deploy', $site);
        $deployment = $promote->execute($site, $request->user());

        return redirect()->route('deployments.show', $deployment)->with('status', 'Staging settings were copied to production and a deployment was queued.');
    }

    public function enableMonitoring(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422, 'The site must be active before enabling monitoring.');

        $data = $request->validate([
            'monitor_path' => ['sometimes', 'string', 'max:200', 'regex:/^\/[A-Za-z0-9._~\-\/]*$/'],
            'monitor_consecutive_failures' => ['sometimes', 'integer', 'between:1,12'],
            'monitor_cooldown_minutes' => ['sometimes', 'integer', 'between:5,1440'],
        ]);

        $site->update([
            'site_monitoring_enabled' => true,
            'monitor_path' => $data['monitor_path'] ?? $site->monitor_path ?: '/',
            'monitor_consecutive_failures' => $data['monitor_consecutive_failures'] ?? $site->monitor_consecutive_failures ?: 3,
            'monitor_cooldown_minutes' => $data['monitor_cooldown_minutes'] ?? $site->monitor_cooldown_minutes ?: 30,
        ]);

        CheckSiteUptimeJob::dispatch($site->id)->onQueue('monitoring');
        CheckSiteDnsJob::dispatch($site->id)->onQueue('monitoring');

        return back()->with('status', 'Site monitoring enabled. Uptime and DNS checks have been queued.');
    }

    public function disableMonitoring(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $site->update([
            'site_monitoring_enabled' => false,
            'monitor_consecutive_down' => 0,
            'monitor_last_error' => null,
            'dns_last_error' => null,
        ]);
        SiteMonitorIncident::where('site_id', $site->id)->where('status', 'open')->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return back()->with('status', 'Site monitoring disabled.');
    }

    public function checkMonitoring(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->site_monitoring_enabled, 422, 'Enable site monitoring before running a check.');
        abort_unless($site->status === 'active', 422, 'The site must be active.');

        CheckSiteUptimeJob::dispatch($site->id)->onQueue('monitoring');
        CheckSiteDnsJob::dispatch($site->id)->onQueue('monitoring');

        return back()->with('status', 'Uptime and DNS checks queued.');
    }

    public function wordpressStatus(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->isWordPress(), 404);
        CheckWordPressInstallJob::dispatch($site->id)->onQueue('operations');

        return back()->with('status', 'Checking whether WordPress has been installed.');
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'repository_url' => ['required', 'string', 'max:2048', new GitRepositoryUrl],
            'branch' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'php_version' => [$site->usesPhp() ? 'required' : 'nullable', Rule::in(config('clouddeck.php_versions'))],
            'deployment_script' => ['nullable', 'string', 'max:30000'],
            'auto_deploy' => ['sometimes', 'boolean'],
            'zero_downtime' => ['sometimes', 'boolean'],
        ]);
        if (! $site->usesPhp()) {
            unset($data['php_version']);
        }
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
