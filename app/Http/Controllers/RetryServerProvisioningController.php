<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Jobs\Servers\BootstrapServerJob;
use App\Jobs\Servers\FinalizeProvisioningJob;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

final class RetryServerProvisioningController extends Controller
{
    public function __invoke(Request $request, Server $server): RedirectResponse
    {
        $this->authorize('update', $server);
        abort_unless($server->provider_id && $server->public_ip && $server->sshKey?->private_key, 422, 'The server needs a provider ID, public IP, and managed SSH key before bootstrap can be retried.');

        $server->update(['status' => ServerStatus::Active, 'progress' => 35, 'current_step' => 'Bootstrap queued', 'failure_reason' => null]);
        Bus::chain([new BootstrapServerJob($server->id), new FinalizeProvisioningJob($server->id)])->onQueue('provisioning')->dispatch();

        return back()->with('status', 'Server bootstrap has been queued again.');
    }
}
