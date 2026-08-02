<?php

namespace App\Http\Controllers;

use App\Jobs\Operations\SyncCronJob;
use App\Models\CronJob;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CronJobController extends Controller
{
    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'expression' => ['required', 'regex:/^(?:[0-9*,\/-]+\s+){4}[0-9*,\/-]+$/'], 'command' => ['required', 'string', 'max:2000', 'not_regex:/[\r\n]/'], 'site_id' => ['nullable', 'uuid', Rule::exists('sites', 'id')->where('user_id', $request->user()->id)->where('server_id', $server->id)]]);
        $cron = $server->cronJobs()->create([...$data, 'user_id' => $request->user()->id, 'enabled' => true]);
        SyncCronJob::dispatch($cron->id)->onQueue('operations');

        return back()->with('status', 'Cron job queued.');
    }

    public function storeForSite(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'expression' => ['required', 'regex:/^(?:[0-9*,\/-]+\s+){4}[0-9*,\/-]+$/'], 'command' => ['required', 'string', 'max:2000', 'not_regex:/[\r\n]/']]);
        $cron = $site->cronJobs()->create([...$data, 'user_id' => $request->user()->id, 'server_id' => $site->server_id, 'enabled' => true]);
        SyncCronJob::dispatch($cron->id)->onQueue('operations');

        return back()->with('status', 'Cron job queued.');
    }

    public function toggle(Request $request, CronJob $cronJob): RedirectResponse
    {
        $this->authorize('update', $cronJob->server);
        $cronJob->update(['enabled' => ! $cronJob->enabled, 'status' => 'pending']);
        SyncCronJob::dispatch($cronJob->id)->onQueue('operations');

        return back()->with('status', 'Cron job update queued.');
    }

    public function destroy(Request $request, CronJob $cronJob): RedirectResponse
    {
        $this->authorize('update', $cronJob->server);
        $cronJob->delete();
        SyncCronJob::dispatch($cronJob->id)->onQueue('operations');

        return back()->with('status', 'Cron job removal queued.');
    }
}
