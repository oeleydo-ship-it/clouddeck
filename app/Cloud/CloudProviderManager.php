<?php

namespace App\Cloud;

use App\Cloud\Contracts\CloudProvider;
use App\Cloud\DigitalOcean\DigitalOceanProvider;
use App\Models\CloudAccount;
use InvalidArgumentException;

final class CloudProviderManager
{
    public function for(CloudAccount $account): CloudProvider
    {
        return match ($account->provider) {
            'digitalocean' => new DigitalOceanProvider((string) data_get($account->credentials, 'token')), default => throw new InvalidArgumentException("Unsupported cloud provider [{$account->provider}].")
        };
    }
}
