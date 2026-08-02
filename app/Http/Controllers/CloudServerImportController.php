<?php

namespace App\Http\Controllers;

use App\Cloud\CloudProviderManager;
use App\Enums\ServerStatus;
use App\Jobs\Servers\BootstrapServerJob;
use App\Jobs\Servers\FinalizeProvisioningJob;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Services\AuditLogger;
use App\Services\QuotaManager;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class CloudServerImportController extends Controller
{
    public function index(Request $request, CloudAccount $cloudAccount, CloudProviderManager $providers): View|RedirectResponse
    {
        $this->authorizeAccount($request, $cloudAccount);

        try {
            $droplets = collect($providers->for($cloudAccount)->servers());
        } catch (ConnectionException) {
            return back()->withErrors(['provider' => 'CloudDeck could not reach DigitalOcean while loading Droplets.']);
        } catch (Throwable) {
            return back()->withErrors(['provider' => 'DigitalOcean could not return the Droplet list. Revalidate the provider token and try again.']);
        }

        $imported = $cloudAccount->servers()->whereNotNull('provider_id')->get()->keyBy('provider_id');

        return view('cloud-accounts.servers', [
            'account' => $cloudAccount,
            'droplets' => $droplets,
            'imported' => $imported,
            'keys' => $request->user()->sshKeys()->whereNotNull('private_key')->latest()->get(),
        ]);
    }

    public function store(Request $request, CloudAccount $cloudAccount, CloudProviderManager $providers, QuotaManager $quotas, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAccount($request, $cloudAccount);
        $data = $request->validate([
            'provider_id' => ['required', 'string', 'max:100'],
            'ssh_key_id' => ['required', 'uuid', Rule::exists('ssh_keys', 'id')->where('user_id', $request->user()->id)->whereNotNull('private_key')],
        ]);
        if ($cloudAccount->servers()->where('provider_id', $data['provider_id'])->exists()) {
            return back()->withErrors(['provider_id' => 'This Droplet is already connected to CloudDeck.']);
        }
        $quotas->assertCanCreate($request->user(), 'servers');

        try {
            $droplet = $providers->for($cloudAccount)->server($data['provider_id']);
        } catch (Throwable) {
            return back()->withErrors(['provider_id' => 'DigitalOcean could not verify this Droplet.']);
        }

        $publicIp = collect(data_get($droplet, 'networks.v4', []))->firstWhere('type', 'public')['ip_address'] ?? null;
        if (($droplet['status'] ?? null) !== 'active' || ! $publicIp) {
            return back()->withErrors(['provider_id' => 'The Droplet must be active and have a public IPv4 address before import.']);
        }

        $hostname = Str::lower((string) ($droplet['name'] ?? 'droplet-'.$data['provider_id']));
        $hostname = trim(preg_replace('/[^a-z0-9-]+/', '-', $hostname) ?? '', '-');
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $hostname)) {
            $hostname = 'droplet-'.$data['provider_id'];
        }

        $teamId = $request->user()->currentTeam?->memberships()->where('user_id', $request->user()->id)->whereNotNull('accepted_at')->exists()
            ? $request->user()->current_team_id
            : null;

        $server = DB::transaction(function () use ($request, $cloudAccount, $data, $droplet, $publicIp, $hostname, $teamId): Server {
            return $request->user()->servers()->create([
                'team_id' => $teamId,
                'cloud_account_id' => $cloudAccount->id,
                'ssh_key_id' => $data['ssh_key_id'],
                'provider_id' => (string) $droplet['id'],
                'name' => (string) $droplet['name'],
                'hostname' => $hostname,
                'region' => (string) data_get($droplet, 'region.slug', 'unknown'),
                'size' => (string) ($droplet['size_slug'] ?? data_get($droplet, 'size.slug', 'unknown')),
                'image' => (string) (data_get($droplet, 'image.slug') ?: data_get($droplet, 'image.id', 'unknown')),
                'status' => ServerStatus::Active,
                'public_ip' => $publicIp,
                'progress' => 30,
                'current_step' => 'Imported; waiting for bootstrap',
                'metadata' => [...$droplet, 'imported_at' => now()->toIso8601String()],
            ]);
        });

        Bus::chain([new BootstrapServerJob($server->id), new FinalizeProvisioningJob($server->id)])->onQueue('provisioning')->dispatch();
        $audit->record($request, 'server.imported', $server, [], ['provider_id' => $server->provider_id, 'cloud_account_id' => $cloudAccount->id]);

        return redirect()->route('dashboard')->with('status', 'Droplet connected. CloudDeck is bootstrapping it now.');
    }

    private function authorizeAccount(Request $request, CloudAccount $cloudAccount): void
    {
        abort_unless($cloudAccount->user_id === $request->user()->id && $cloudAccount->validated_at, 403);
    }
}
