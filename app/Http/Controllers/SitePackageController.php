<?php

namespace App\Http\Controllers;

use App\Jobs\Sites\CheckSitePackagesJob;
use App\Jobs\Sites\InstallLaravelPackageJob;
use App\Jobs\Sites\UpdateHorizonAdminsJob;
use App\Models\Site;
use App\Services\ReverbEnvironment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SitePackageController extends Controller
{
    private const PACKAGES = [
        'laravel/horizon' => 'horizon:install',
        'laravel/reverb' => 'reverb:install',
    ];

    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422, 'Deploy the site at least once before installing a package.');
        $data = $request->validate(['package' => ['required', Rule::in(array_keys(self::PACKAGES))]]);
        $command = $site->terminalCommands()->create(['user_id' => $request->user()->id, 'command' => 'composer require '.$data['package']]);
        InstallLaravelPackageJob::dispatch($command->id, $data['package'], self::PACKAGES[$data['package']]);
        $site->update(['managed_packages' => collect($site->managed_packages ?? [])->push($data['package'])->unique()->values()->all()]);

        $note = '';
        if ($data['package'] === 'laravel/reverb') {
            $port = app(ReverbEnvironment::class)->apply($site);
            $note = ' Reverb credentials were written to this site\'s environment — redeploy to apply them to the release .env. Add a Reverb worker on port '.$port.' from the server\'s Workers tab to start the server and publish it through Nginx.';
        }

        return redirect()->route('sites.remote', ['site' => $site, 'tab' => 'terminal'])->with('status', 'Installing '.$data['package'].'. Uplary will keep it installed on every future deployment.'.$note.' Output appears in the terminal tab.');
    }

    public function destroy(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate(['package' => ['required', Rule::in(array_keys(self::PACKAGES))]]);
        $site->update(['managed_packages' => collect($site->managed_packages ?? [])->reject(fn ($p) => $p === $data['package'])->values()->all()]);

        return back()->with('status', 'Uplary will no longer reinstall '.$data['package'].' on future deployments. It remains in the current release until removed manually.');
    }

    public function check(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422, 'Deploy the site at least once before checking installed packages.');
        CheckSitePackagesJob::dispatch($site->id);

        return back()->with('status', 'Checking installed packages.');
    }

    public function horizonAdmins(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($site->status === 'active', 422, 'Deploy the site at least once before managing Horizon admins.');
        $data = $request->validate(['emails' => ['nullable', 'string', 'max:5000']]);
        $emails = collect(preg_split('/[\s,]+/', (string) ($data['emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
        $site->update(['horizon_admin_emails' => $emails]);
        UpdateHorizonAdminsJob::dispatch($site->id);

        return back()->with('status', 'Horizon dashboard access updated. Takes effect immediately, no redeploy needed.');
    }
}
