<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonitoringAgentController extends Controller
{
    public function __invoke(Server $server): BinaryFileResponse
    {
        $this->authorize('view', $server);

        return response()->download(resource_path('scripts/clouddeck-monitor.sh'), 'clouddeck-monitor.sh', ['Content-Type' => 'text/x-shellscript']);
    }
}
