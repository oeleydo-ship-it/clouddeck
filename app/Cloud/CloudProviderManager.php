<?php

namespace App\Cloud;

use App\Cloud\Contracts\CloudProvider;
use App\Cloud\DigitalOcean\DigitalOceanProvider;
use App\Cloud\Exceptions\CloudCredentialException;
use App\Models\CloudAccount;
use App\Models\Server;
use App\Services\SystemSettings;
use InvalidArgumentException;

final class CloudProviderManager
{
    public function for(CloudAccount $account): CloudProvider
    {
        return $this->make($account->provider, (string) data_get($account->credentials, 'token'));
    }

    /**
     * Platform-owned cloud credentials (managed servers). Never uses a customer CloudAccount.
     */
    public function forPlatform(?SystemSettings $settings = null): CloudProvider
    {
        $settings ??= app(SystemSettings::class);
        $token = $settings->managedCloudToken();
        if (! filled($token)) {
            throw new CloudCredentialException('Managed servers are not configured. Add a platform cloud API token under Admin → Settings.');
        }

        return $this->make($settings->managedCloudProvider(), $token);
    }

    /**
     * Resolve the provider for create/wait/destroy/snapshot based on how the server was provisioned.
     */
    public function forServer(Server $server): CloudProvider
    {
        if ($server->isManaged()) {
            return $this->forPlatform();
        }

        if (! $server->cloudAccount) {
            throw new InvalidArgumentException('This server has no cloud provider connection.');
        }

        return $this->for($server->cloudAccount);
    }

    private function make(string $provider, string $token): CloudProvider
    {
        return match ($provider) {
            'digitalocean' => new DigitalOceanProvider($token),
            default => throw new InvalidArgumentException("Unsupported cloud provider [{$provider}]."),
        };
    }
}
