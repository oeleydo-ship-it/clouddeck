<?php

namespace App\Http\Controllers;

use App\Jobs\RemoteManagement\RunTerminalCommandJob;
use App\Models\Site;
use App\Services\SafeTerminalCommand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function store(Request $request, Site $site, SafeTerminalCommand $safeCommand): RedirectResponse
    {
        $this->authorize('update', $site);
        $command = $request->validate(['command' => ['required', 'string', 'max:1000']])['command'];
        $safeCommand->compile($command);
        $record = $site->terminalCommands()->create(['user_id' => $request->user()->id, 'command' => $command]);
        RunTerminalCommandJob::dispatch($record->id);

        return redirect()->route('sites.remote', ['site' => $site, 'tab' => 'terminal'])->with('status', 'Command queued.');
    }
}
