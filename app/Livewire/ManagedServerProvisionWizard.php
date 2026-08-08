<?php

namespace App\Livewire;

use App\Actions\Servers\ProvisionServer;
use App\Cloud\CloudProviderManager;
use App\Cloud\Exceptions\CloudCredentialException;
use App\Enums\ServerStatus;
use App\Services\FeatureManager;
use App\Services\QuotaManager;
use App\Services\SshKeyGenerator;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Provision a VPS on the platform cloud account — no customer provider connection required.
 */
#[Layout('layouts.app')]
class ManagedServerProvisionWizard extends Component
{
    public int $step = 1;

    public string $sshKeyId = '';

    public string $region = '';

    public string $size = '';

    public string $image = 'ubuntu-24-04-x64';

    public string $name = '';

    public string $hostname = '';

    public array $regions = [];

    public array $sizes = [];

    public array $images = [];

    public bool $loadingCatalog = false;

    public string $catalogError = '';

    public function mount(CloudProviderManager $providers, SystemSettings $settings, FeatureManager $features, SshKeyGenerator $generator): void
    {
        abort_unless($settings->managedServersReady(), 404);
        abort_unless($features->enabled('managed_servers', Auth::user()), 403, 'Managed servers are not included in your plan.');

        $this->ensureManagedSshKey($generator);
        $this->loadCatalog($providers);
    }

    public function next(): void
    {
        $this->validateStep();
        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function deploy(ProvisionServer $provision, QuotaManager $quotas): mixed
    {
        $this->validate($this->rules());
        abort_unless(app(SystemSettings::class)->managedServersReady(), 404);
        abort_unless(app(FeatureManager::class)->enabled('managed_servers', Auth::user()), 403);

        $quotas->assertCanCreate(Auth::user(), 'managed_servers');
        abort_unless(collect($this->regions)->contains('slug', $this->region), 422);
        abort_unless(collect($this->sizes)->contains('slug', $this->size), 422);
        abort_unless(collect($this->images)->contains('slug', $this->image), 422);

        $settings = app(SystemSettings::class);
        $sizeCatalog = collect($this->sizes)->firstWhere('slug', $this->size) ?? [];

        $server = Auth::user()->servers()->create([
            'team_id' => Auth::user()->currentTeam?->memberships()->where('user_id', Auth::id())->whereNotNull('accepted_at')->exists() ? Auth::user()->current_team_id : null,
            'cloud_account_id' => null,
            'provisioning_source' => 'managed',
            'ssh_key_id' => $this->sshKeyId,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'region' => $this->region,
            'size' => $this->size,
            'image' => $this->image,
            'status' => ServerStatus::Pending,
            'current_step' => 'Queued',
            'metadata' => [
                'platform_provider' => $settings->managedCloudProvider(),
                'billed_as' => 'managed',
                'infra_price_monthly' => (float) ($sizeCatalog['price_monthly'] ?? 0),
                'customer_price_monthly' => $settings->managedServerPrice($sizeCatalog),
            ],
        ]);
        $provision->execute($server);
        session()->flash('status', 'Managed server provisioning has started.');

        return redirect()->route('servers.manage', $server);
    }

    private function loadCatalog(CloudProviderManager $providers): void
    {
        $this->loadingCatalog = true;
        $this->catalogError = '';
        try {
            $provider = $providers->forPlatform();
            $this->regions = collect($provider->regions())->where('available', true)->sortBy('name')->values()->all();
            $this->sizes = collect($provider->sizes())->sortBy('price_monthly')->values()->all();
            $this->images = collect($provider->images())->where('distribution', 'Ubuntu')->filter(fn (array $item) => $item['slug'] ?? false)->sortByDesc('created_at')->values()->all();
            $this->image = collect($this->images)->firstWhere('slug', 'ubuntu-24-04-x64')['slug'] ?? ($this->images[0]['slug'] ?? '');
        } catch (CloudCredentialException|Throwable $e) {
            $this->catalogError = $e instanceof CloudCredentialException
                ? $e->getMessage()
                : 'Unable to load the managed server catalog. Ask an administrator to check the platform cloud API token.';
        } finally {
            $this->loadingCatalog = false;
        }
    }

    private function ensureManagedSshKey(SshKeyGenerator $generator): void
    {
        $user = Auth::user();
        $platform = app(SystemSettings::class)->branding()['name'];
        $keyName = $platform.' managed';
        $existing = $user->sshKeys()->where('name', $keyName)->whereNotNull('private_key')->first()
            ?? $user->sshKeys()->whereIn('name', ['Uplary managed', 'CloudDeck managed'])->whereNotNull('private_key')->first();

        if (! $existing) {
            $pair = $generator->generate($platform.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'));
            $existing = $user->sshKeys()->create(['name' => $keyName, ...$pair]);
        }

        $this->sshKeyId = (string) $existing->id;
    }

    private function validateStep(): void
    {
        $fields = match ($this->step) {
            1 => ['region', 'size', 'image'],
            2 => ['sshKeyId'],
            3 => ['name', 'hostname'],
            default => array_keys($this->rules()),
        };
        $this->validate(collect($this->rules())->only($fields)->all());
    }

    private function rules(): array
    {
        $user = Auth::user();

        return [
            'region' => ['required', 'string', 'max:50'],
            'size' => ['required', 'string', 'max:50'],
            'image' => ['required', 'string', 'max:80'],
            'sshKeyId' => ['required', 'uuid', Rule::exists('ssh_keys', 'id')->where('user_id', $user->id)],
            'name' => ['required', 'string', 'max:100'],
            'hostname' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/'],
        ];
    }

    public function render()
    {
        $settings = app(SystemSettings::class);
        $selectedSize = collect($this->sizes)->firstWhere('slug', $this->size);

        return view('livewire.managed-server-provision-wizard', [
            'branding' => $settings->branding(),
            'keys' => Auth::user()->sshKeys()->latest()->get(),
            'platform' => $settings->branding()['name'],
            'selectedRegion' => collect($this->regions)->firstWhere('slug', $this->region),
            'selectedSize' => $selectedSize,
            'selectedImage' => collect($this->images)->firstWhere('slug', $this->image),
            'selectedKey' => Auth::user()->sshKeys()->whereKey($this->sshKeyId)->first(),
            'customerPrice' => $selectedSize ? $settings->managedServerPrice($selectedSize) : null,
        ]);
    }
}
