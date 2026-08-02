<?php

namespace App\Actions\Servers;

use App\Jobs\Servers\BootstrapServerJob;
use App\Jobs\Servers\CreateDropletJob;
use App\Jobs\Servers\FinalizeProvisioningJob;
use App\Jobs\Servers\WaitForServerJob;
use App\Models\Server;
use Illuminate\Support\Facades\Bus;

final class ProvisionServer
{
    public function execute(Server $server): void
    {
        Bus::chain([new CreateDropletJob($server->id), new WaitForServerJob($server->id), new BootstrapServerJob($server->id), new FinalizeProvisioningJob($server->id)])->onQueue('provisioning')->dispatch();
    }
}
