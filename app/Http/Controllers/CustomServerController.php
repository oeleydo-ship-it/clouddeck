<?php

namespace App\Http\Controllers;

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
use Illuminate\View\View;

/**
 * Attaching a server the operator already runs, anywhere — another provider, a bare metal
 * box, a VM under a desk. CloudDeck never creates it, so there is no provider API and no
 * token: the operator authorises our public key on their root account and gives us an
 * address to reach it at.
 */
class CustomServerController extends Controller
{
    public function create(Request $request, SshKeyGenerator $generator): View
    {
        return view('servers.custom', ['key' => $this->managedKey($request, $generator)]);
    }

    public function store(Request $request, QuotaManager $quotas, SshKeyGenerator $generator, AuditLogger $audit): RedirectResponse
    {
        $quotas->assertCanCreate($request->user(), 'servers');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // Named hosts are deliberately not accepted: DNS can move under us later, and
            // the address is what every future SSH connection depends on.
            'public_ip' => ['required', 'ip', Rule::unique('servers', 'public_ip')->whereNull('deleted_at')],
            'ssh_port' => ['required', 'integer', 'between:1,65535'],
            'image' => ['required', Rule::in(['ubuntu-24-04-x64', 'ubuntu-22-04-x64'])],
        ]);

        $server = $request->user()->servers()->create([
            'cloud_account_id' => null,
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
        $existing = $request->user()->sshKeys()->where('name', 'CloudDeck managed')->first();

        if ($existing) {
            return $existing;
        }

        $pair = $generator->generate('clouddeck@'.parse_url(config('app.url'), PHP_URL_HOST));

        return $request->user()->sshKeys()->create(['name' => 'CloudDeck managed', ...$pair]);
    }
}
