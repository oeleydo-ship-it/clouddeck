<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Team;
use App\Services\AuditLogger;
use App\Services\TeamAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ServerTeamController extends Controller
{
    public function update(Request $request, Server $server, TeamAccess $teams, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('transfer', $server);
        $data = $request->validate([
            'team_id' => ['nullable', 'uuid', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'confirmation' => ['required', Rule::in([$server->hostname])],
        ]);

        if ($data['team_id'] ?? null) {
            $target = Team::findOrFail($data['team_id']);
            abort_unless($teams->canManage($request->user(), $target), 403);
        } else {
            abort_unless($server->user_id === $request->user()->id, 403);
        }

        $oldTeam = $server->team_id;
        $server->update(['team_id' => $data['team_id'] ?? null]);
        $audit->record($request, 'server.team_transferred', $server, ['team_id' => $oldTeam], ['team_id' => $server->team_id]);

        return back()->with('status', $server->team_id ? 'Server transferred to the team.' : 'Server moved to your personal workspace.');
    }
}
