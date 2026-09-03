<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonitoringAgentController extends Controller
{
    public function __invoke(Server $server): BinaryFileResponse
    {
        $this->authorize('view', $server);

        return response()->download(resource_path('scripts/uplary-monitor.sh'), 'uplary-monitor.sh', ['Content-Type' => 'text/x-shellscript']);
    }
}
