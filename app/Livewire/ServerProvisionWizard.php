<?php

namespace App\Livewire;

use App\Actions\Servers\ProvisionServer;
use App\Cloud\CloudProviderManager;
use App\Enums\ServerStatus;
use App\Services\QuotaManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class ServerProvisionWizard extends Component
{
    public int $step = 1;

    public string $cloudAccountId = '';

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

    public function updatedCloudAccountId(CloudProviderManager $providers): void
    {
        $this->reset(['regions', 'sizes', 'images', 'region', 'size']);
        if (! $this->cloudAccountId) {
            return;
        }

        $this->loadingCatalog = true;
        try {
            $account = Auth::user()->cloudAccounts()->whereKey($this->cloudAccountId)->firstOrFail();
            $provider = $providers->for($account);
            $this->regions = collect($provider->regions())->where('available', true)->sortBy('name')->values()->all();
            $this->sizes = collect($provider->sizes())->sortBy('price_monthly')->values()->all();
            $this->images = collect($provider->images())->where('distribution', 'Ubuntu')->filter(fn (array $item) => $item['slug'] ?? false)->sortByDesc('created_at')->values()->all();
            $this->image = collect($this->images)->firstWhere('slug', 'ubuntu-24-04-x64')['slug'] ?? ($this->images[0]['slug'] ?? '');
        } catch (Throwable) {
            $this->addError('cloudAccountId', 'Unable to retrieve the provider catalog. Check the account and try again.');
        } finally {
            $this->loadingCatalog = false;
        }
    }

    public function next(): void
    {
        $this->validateStep();
        $this->step = min(5, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function deploy(ProvisionServer $provision, QuotaManager $quotas): mixed
    {
        $this->validate($this->rules());
        $quotas->assertCanCreate(Auth::user(), 'servers');
        abort_unless(collect($this->regions)->contains('slug', $this->region), 422);
        abort_unless(collect($this->sizes)->contains('slug', $this->size), 422);
        abort_unless(collect($this->images)->contains('slug', $this->image), 422);

        $server = Auth::user()->servers()->create([
            'team_id' => Auth::user()->currentTeam?->memberships()->where('user_id', Auth::id())->whereNotNull('accepted_at')->exists() ? Auth::user()->current_team_id : null,
            'cloud_account_id' => $this->cloudAccountId,
            'ssh_key_id' => $this->sshKeyId,
            'name' => $this->name,
            'hostname' => $this->hostname,
            'region' => $this->region,
            'size' => $this->size,
            'image' => $this->image,
            'status' => ServerStatus::Pending,
            'current_step' => 'Queued',
            'provisioning_source' => 'byos',
        ]);
        $provision->execute($server);
        session()->flash('status', 'Server provisioning has started.');

        return redirect()->route('dashboard');
    }

    private function validateStep(): void
    {
        $fields = match ($this->step) {
            1 => ['cloudAccountId'],
            2 => ['region', 'size', 'image'],
            3 => ['sshKeyId'],
            4 => ['name', 'hostname'],
            default => array_keys($this->rules()),
        };
        $this->validate(collect($this->rules())->only($fields)->all());
    }

    private function rules(): array
    {
        $user = Auth::user();

        return [
            'cloudAccountId' => ['required', 'uuid', Rule::exists('cloud_accounts', 'id')->where('user_id', $user->id)->whereNotNull('validated_at')],
            'region' => ['required', 'string', 'max:50'],
            'size' => ['required', 'string', 'max:50'],
            'image' => ['required', 'string', 'max:100'],
            'sshKeyId' => ['required', 'uuid', Rule::exists('ssh_keys', 'id')->where('user_id', $user->id)->whereNotNull('private_key')],
            'name' => ['required', 'string', 'max:100'],
            'hostname' => ['required', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/'],
        ];
    }

    public function render()
    {
        return view('livewire.server-provision-wizard', [
            'accounts' => Auth::user()->cloudAccounts()->whereNotNull('validated_at')->get(),
            'keys' => Auth::user()->sshKeys()->whereNotNull('private_key')->get(),
        ])->title('Provision server · '.app(\App\Services\SystemSettings::class)->branding()['name']);
    }
}
