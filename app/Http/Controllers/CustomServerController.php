<?php

namespace App\Http\Controllers;

use App\Actions\Servers\ReconnectServer;
use App\Enums\ServerStatus;
use App\Jobs\Servers\ConnectCustomServerJob;
use App\Models\Server;
use App\Models\SshKey;
use App\Services\AuditLogger;
use App\Services\QuotaManager;
use App\Services\SshKeyGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Attaching a server the operator already runs, anywhere — another provider, a bare metal
 * box, a VM under a desk. Uplary never creates it, so there is no provider API and no
 * token: the operator authorises our public key on their root account and gives us an
 * address to reach it at.
 */
class CustomServerController extends Controller
{
    public function create(Request $request, SshKeyGenerator $generator)
    {
        $managedKey = $this->managedKey($request, $generator);

        return Inertia::render('Servers/Custom', [
            'title' => 'Add a server by IP',
            'sshKey' => [
                'id' => $managedKey->id,
                'name' => $managedKey->name,
                'public_key' => $managedKey->public_key,
            ],
            'authorizedKeysPath' => 'authorized_keys',
            'sshNote' => 'Servers attached by IP over SSH',
            'removedServers' => $request->user()->servers()
                ->onlyTrashed()
                ->orderByDesc('deleted_at')
                ->get(['id', 'name', 'hostname', 'public_ip', 'ssh_port', 'provisioned_at', 'deleted_at', 'provisioning_source']),
            // Carried over when the operator arrived from connecting a provider Uplary
            // cannot drive, so they do not retype the address they just gave us.
            'account' => $request->user()->cloudAccounts()->find($request->query('cloud_account')),
        ]);
    }

    public function store(Request $request, QuotaManager $quotas, SshKeyGenerator $generator, AuditLogger $audit, ReconnectServer $reconnect): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'reconnect_server_id' => ['nullable', 'uuid', Rule::exists('servers', 'id')->where('user_id', $request->user()->id)],
            'reinstall_stack' => ['sometimes', 'boolean'],
            // Named hosts are deliberately not accepted: DNS can move under us later, and
            // the address is what every future SSH connection depends on.
            'public_ip' => ['required', 'ip'],
            'ssh_port' => ['required', 'integer', 'between:1,65535'],
            'image' => ['required', Rule::in(['ubuntu-26-04-x64', 'ubuntu-24-04-x64', 'ubuntu-22-04-x64'])],
            // Scoped to the operator's own accounts so a guessed id cannot file a server
            // under somebody else's provider connection.
            'cloud_account_id' => ['nullable', 'uuid', Rule::exists('cloud_accounts', 'id')->where('user_id', $request->user()->id)],
        ]);

        if ($data['reconnect_server_id'] ?? null) {
            $removed = Server::onlyTrashed()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['reconnect_server_id']);

            $request->validate([
                'public_ip' => [Rule::unique('servers', 'public_ip')->whereNull('deleted_at')->ignore($removed->id)],
            ]);

            $server = $reconnect->execute($removed, [
                'name' => $data['name'],
                'public_ip' => $data['public_ip'],
                'ssh_port' => $data['ssh_port'],
                'image' => $data['image'],
                'cloud_account_id' => $data['cloud_account_id'] ?? $removed->cloud_account_id,
                'ssh_key_id' => $this->managedKey($request, $generator)->id,
            ], (bool) ($data['reinstall_stack'] ?? false));
            $audit->record($request, 'server.reconnected', $server, [], ['public_ip' => $server->public_ip]);

            return redirect()->route('servers.manage', $server)->with('status', 'Reconnecting to your server. Uplary will verify SSH and restore the previous record.');
        }

        $request->validate([
            'public_ip' => [Rule::unique('servers', 'public_ip')->whereNull('deleted_at')],
        ]);

        if ($removed = Server::onlyTrashed()->where('user_id', $request->user()->id)->where('public_ip', $data['public_ip'])->first()) {
            return back()
                ->withErrors([
                    'public_ip' => "This address belonged to {$removed->name}, which was removed from Uplary. Reconnect that server instead of creating a new one.",
                ])
                ->with('reconnect_server_id', $removed->id);
        }

        $quotas->assertCanCreate($request->user(), 'servers');

        $server = $request->user()->servers()->create([
            'cloud_account_id' => $data['cloud_account_id'] ?? null,
            'ssh_key_id' => $this->managedKey($request, $generator)->id,
            'name' => $data['name'],
            'hostname' => Str::slug($data['name']) ?: 'server',
            'region' => 'custom',
            'size' => 'custom',
            'image' => $data['image'],
            'public_ip' => $data['public_ip'],
            'ssh_port' => $data['ssh_port'],
            'status' => ServerStatus::Pending,
            'current_step' => 'Waiting to verify the connection',
            'provisioning_source' => 'custom',
        ]);

        ConnectCustomServerJob::dispatch($server->id)->onQueue('provisioning');
        $audit->record($request, 'server.custom_attached', $server, [], ['public_ip' => $server->public_ip]);

        return redirect()->route('servers.manage', $server)->with('status', 'Verifying the connection and provisioning. Watch the progress here.');
    }

    /**
     * One managed key per operator, reused for every custom server, so the instructions on
     * screen stay valid and a second server does not mean authorising a second key.
     */
    private function managedKey(Request $request, SshKeyGenerator $generator): SshKey
    {
        $platform = app(\App\Services\SystemSettings::class)->branding()['name'];
        $keyName = $platform.' managed';
        $existing = $request->user()->sshKeys()->where('name', $keyName)->first();

        if ($existing) {
            return $existing;
        }

        // Fall back to the previous default name so re-provisioning after a rebrand
        // does not mint a second key the operator already authorised on their servers.
        $existing = $request->user()->sshKeys()->whereIn('name', ['Uplary managed', 'CloudDeck managed'])->whereNotNull('private_key')->first();
        if ($existing) {
            return $existing;
        }

        $pair = $generator->generate($platform.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));

        return $request->user()->sshKeys()->create(['name' => $keyName, ...$pair]);
    }
}
