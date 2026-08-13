<?php

namespace App\Http\Controllers;

use App\Actions\Servers\ProvisionServer;
use App\Billing\Stripe\StripeClient;
use App\Cloud\CloudProviderManager;
use App\Cloud\Exceptions\CloudCredentialException;
use App\Enums\ServerStatus;
use App\Services\FeatureManager;
use App\Services\QuotaManager;
use App\Services\SshKeyGenerator;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ManagedServerProvisionController extends Controller
{
    public function create(Request $request, CloudProviderManager $providers, SystemSettings $settings, FeatureManager $features, SshKeyGenerator $generator): Response
    {
        abort_unless($settings->managedServersReady(), 404);
        abort_unless($features->enabled('managed_servers', $request->user()), 403, 'Managed servers are not included in your plan.');

        $key = $this->ensureManagedSshKey($request, $generator, $settings);
        $catalogError = null;
        $regions = $sizes = $images = [];

        try {
            $provider = $providers->forPlatform();
            $regions = collect($provider->regions())->where('available', true)->sortBy('name')->values()->all();
            $sizes = collect($provider->sizes())->sortBy('price_monthly')->values()->all();
            $images = collect($provider->images())->where('distribution', 'Ubuntu')->filter(fn (array $item) => $item['slug'] ?? false)->sortByDesc('created_at')->values()->all();
        } catch (CloudCredentialException|Throwable $e) {
            $catalogError = $e instanceof CloudCredentialException
                ? $e->getMessage()
                : 'Unable to load the managed server catalog. Ask an administrator to check the platform cloud API token.';
        }

        $pricedSizes = collect($sizes)->map(function (array $size) use ($settings) {
            $size['customer_price_monthly'] = (float) $settings->managedServerPrice($size);

            return $size;
        })->all();

        return Inertia::render('Servers/Managed', [
            'title' => 'Provision a managed server',
            'keys' => $request->user()->sshKeys()->latest()->get(),
            'defaultKeyId' => $key->id,
            'regions' => $regions,
            'sizes' => $pricedSizes,
            'images' => $images,
            'catalogError' => $catalogError,
            'stripeEnabled' => (bool) config('services.stripe.secret'),
            'platform' => $settings->branding()['name'],
        ]);
    }

    public function store(Request $request, QuotaManager $quotas, StripeClient $stripe, ProvisionServer $provision, CloudProviderManager $providers, SystemSettings $settings, FeatureManager $features): RedirectResponse
    {
        abort_unless($settings->managedServersReady(), 404);
        abort_unless($features->enabled('managed_servers', $request->user()), 403);

        $user = $request->user();
        $data = $request->validate([
            'region' => ['required', 'string', 'max:50'],
            'size' => ['required', 'string', 'max:50'],
            'image' => ['required', 'string', 'max:80'],
            'ssh_key_id' => ['required', 'uuid', Rule::exists('ssh_keys', 'id')->where('user_id', $user->id)],
            'name' => ['required', 'string', 'max:100'],
            'hostname' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/'],
        ]);

        $quotas->assertCanCreate($user, 'managed_servers');

        try {
            $provider = $providers->forPlatform();
            $regions = collect($provider->regions());
            $sizes = collect($provider->sizes());
            $images = collect($provider->images());
        } catch (Throwable) {
            return back()->withErrors(['catalog' => 'Unable to load the managed server catalog.']);
        }

        abort_unless($regions->contains('slug', $data['region']), 422);
        abort_unless($sizes->contains('slug', $data['size']), 422);
        abort_unless($images->contains('slug', $data['image']), 422);

        $sizeCatalog = $sizes->firstWhere('slug', $data['size']) ?? [];
        $customerPrice = (float) $settings->managedServerPrice($sizeCatalog);
        $amountCents = (int) round($customerPrice * 100);
        $platform = $settings->branding()['name'];

        $server = $user->servers()->create([
            'team_id' => $user->currentTeam?->memberships()->where('user_id', $user->id)->whereNotNull('accepted_at')->exists() ? $user->current_team_id : null,
            'cloud_account_id' => null,
            'provisioning_source' => 'managed',
            'ssh_key_id' => $data['ssh_key_id'],
            'name' => $data['name'],
            'hostname' => $data['hostname'],
            'region' => $data['region'],
            'size' => $data['size'],
            'image' => $data['image'],
            'status' => $amountCents > 0 ? ServerStatus::AwaitingPayment : ServerStatus::Pending,
            'current_step' => $amountCents > 0 ? 'Awaiting payment' : 'Queued',
            'metadata' => [
                'platform_provider' => $settings->managedCloudProvider(),
                'billed_as' => 'managed',
                'infra_price_monthly' => (float) ($sizeCatalog['price_monthly'] ?? 0),
                'customer_price_monthly' => $customerPrice,
                'payment_status' => $amountCents > 0 ? 'unpaid' : 'waived',
            ],
        ]);

        if ($amountCents <= 0) {
            $provision->execute($server);

            return redirect()->route('servers.manage', $server)->with('status', 'Managed server provisioning has started.');
        }

        if (! config('services.stripe.secret')) {
            $server->delete();
            throw ValidationException::withMessages([
                'billing' => 'Managed servers require Stripe payment. Ask an administrator to configure Stripe under Admin → Payments.',
            ]);
        }

        try {
            $session = $stripe->checkoutManagedServer(
                $user,
                $server,
                $amountCents,
                $platform.' managed server · '.$server->name,
            );
        } catch (RuntimeException $e) {
            $server->delete();
            throw ValidationException::withMessages(['billing' => $e->getMessage()]);
        } catch (Throwable) {
            $server->delete();
            throw ValidationException::withMessages(['billing' => 'Unable to start checkout. Please try again or contact support.']);
        }

        $server->forceFill([
            'metadata' => array_merge($server->metadata ?? [], [
                'stripe_checkout_session_id' => $session['id'] ?? null,
            ]),
        ])->save();

        return redirect()->away($session['url']);
    }

    private function ensureManagedSshKey(Request $request, SshKeyGenerator $generator, SystemSettings $settings)
    {
        $user = $request->user();
        $platform = $settings->branding()['name'];
        $keyName = $platform.' managed';
        $existing = $user->sshKeys()->where('name', $keyName)->whereNotNull('private_key')->first()
            ?? $user->sshKeys()->whereIn('name', ['Uplary managed', 'CloudDeck managed'])->whereNotNull('private_key')->first();

        if (! $existing) {
            $pair = $generator->generate($platform.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));
            $existing = $user->sshKeys()->create(['name' => $keyName, ...$pair]);
        }

        return $existing;
    }
}
