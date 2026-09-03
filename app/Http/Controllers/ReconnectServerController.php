<?php

namespace App\Http\Controllers;

use App\Actions\Servers\ReconnectServer;
use App\Models\Server;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ReconnectServerController extends Controller
{
    public function __invoke(Request $request, string $server, ReconnectServer $reconnect, AuditLogger $audit): RedirectResponse
    {
        $removed = Server::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->findOrFail($server);

        $data = $request->validate([
            'public_ip' => ['sometimes', 'ip', Rule::unique('servers', 'public_ip')->whereNull('deleted_at')->ignore($removed->id)],
            'ssh_port' => ['sometimes', 'integer', 'between:1,65535'],
            'ssh_key_id' => ['sometimes', 'uuid', Rule::exists('ssh_keys', 'id')->where('user_id', $request->user()->id)->whereNotNull('private_key')],
        ]);

        $updates = collect($data)->only(['public_ip', 'ssh_port', 'ssh_key_id'])->filter(fn ($value) => $value !== null)->all();

        $server = $reconnect->execute($removed, $updates);
        $audit->record($request, 'server.reconnected', $server, [], ['public_ip' => $server->public_ip, 'provider_id' => $server->provider_id]);

        return redirect()->route('servers.manage', $server)->with('status', 'Reconnecting to the server. Watch progress here — existing sites and data on the host are kept when the machine was only removed from Uplary.');
    }
}
