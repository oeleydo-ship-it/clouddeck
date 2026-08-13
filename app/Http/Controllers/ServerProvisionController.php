<?php

namespace App\Http\Controllers;

use App\Actions\Servers\ProvisionServer;
use App\Cloud\CloudProviderManager;
use App\Enums\ServerStatus;
use App\Models\CloudAccount;
use App\Services\QuotaManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ServerProvisionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Servers/Create', [
            'title' => 'Provision a server',
            'accounts' => $request->user()->cloudAccounts()->whereNotNull('validated_at')->latest()->get(),
            'keys' => $request->user()->sshKeys()->whereNotNull('private_key')->latest()->get(),
        ]);
    }

    public function catalog(Request $request, CloudAccount $cloudAccount, CloudProviderManager $providers): JsonResponse
    {
        abort_unless($cloudAccount->user_id === $request->user()->id && $cloudAccount->validated_at, 404);

        try {
            $provider = $providers->for($cloudAccount);

            return response()->json([
                'regions' => collect($provider->regions())->where('available', true)->sortBy('name')->values()->all(),
                'sizes' => collect($provider->sizes())->sortBy('price_monthly')->values()->all(),
                'images' => collect($provider->images())->where('distribution', 'Ubuntu')->filter(fn (array $item) => $item['slug'] ?? false)->sortByDesc('created_at')->values()->all(),
            ]);
        } catch (Throwable) {
            return response()->json(['message' => 'Unable to retrieve the provider catalog. Check the account and try again.'], 422);
        }
    }

    public function store(Request $request, ProvisionServer $provision, QuotaManager $quotas, CloudProviderManager $providers): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'cloud_account_id' => ['required', 'uuid', Rule::exists('cloud_accounts', 'id')->where('user_id', $user->id)->whereNotNull('validated_at')],
            'region' => ['required', 'string', 'max:50'],
            'size' => ['required', 'string', 'max:50'],
            'image' => ['required', 'string', 'max:100'],
            'ssh_key_id' => ['required', 'uuid', Rule::exists('ssh_keys', 'id')->where('user_id', $user->id)->whereNotNull('private_key')],
            'name' => ['required', 'string', 'max:100'],
            'hostname' => ['required', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
        ]);

        $quotas->assertCanCreate($user, 'servers');

        $account = $user->cloudAccounts()->whereKey($data['cloud_account_id'])->whereNotNull('validated_at')->firstOrFail();
        try {
            $provider = $providers->for($account);
            $regions = collect($provider->regions());
            $sizes = collect($provider->sizes());
            $images = collect($provider->images());
        } catch (Throwable) {
            return back()->withErrors(['cloud_account_id' => 'Unable to retrieve the provider catalog. Check the account and try again.']);
        }

        abort_unless($regions->contains('slug', $data['region']), 422);
        abort_unless($sizes->contains('slug', $data['size']), 422);
        abort_unless($images->contains('slug', $data['image']), 422);

        $server = $user->servers()->create([
            'team_id' => $user->currentTeam?->memberships()->where('user_id', $user->id)->whereNotNull('accepted_at')->exists() ? $user->current_team_id : null,
            'cloud_account_id' => $data['cloud_account_id'],
            'ssh_key_id' => $data['ssh_key_id'],
            'name' => $data['name'],
            'hostname' => $data['hostname'],
            'region' => $data['region'],
            'size' => $data['size'],
            'image' => $data['image'],
            'status' => ServerStatus::Pending,
            'current_step' => 'Queued',
            'provisioning_source' => 'byos',
        ]);
        $provision->execute($server);

        return redirect()->route('dashboard')->with('status', 'Server provisioning has started.');
    }
}
