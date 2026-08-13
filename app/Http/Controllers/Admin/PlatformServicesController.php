<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PlatformRuntime\PlatformServicesMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlatformServicesController extends Controller
{
    public function index(PlatformServicesMonitor $monitor)
    {
        return Inertia::render('Admin/PlatformServices', [
            'title' => 'Platform services',
            'heading' => 'Control-plane runtime',
            'renewLabel' => 'Renew certificate',
            'initial' => $monitor->status(),
        ]);
    }

    public function status(PlatformServicesMonitor $monitor): JsonResponse
    {
        return response()->json($monitor->status());
    }

    public function start(Request $request, string $service, PlatformServicesMonitor $monitor, AuditLogger $audit): JsonResponse
    {
        abort_unless(in_array($service, PlatformServicesMonitor::SERVICES, true), 404);

        $result = $monitor->start($service);
        $audit->record($request, 'platform-services.start', null, [], [
            'service' => $service,
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function stop(Request $request, string $service, PlatformServicesMonitor $monitor, AuditLogger $audit): JsonResponse
    {
        abort_unless(in_array($service, PlatformServicesMonitor::SERVICES, true), 404);

        $result = $monitor->stop($service);
        $audit->record($request, 'platform-services.stop', null, [], [
            'service' => $service,
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function restart(Request $request, string $service, PlatformServicesMonitor $monitor, AuditLogger $audit): JsonResponse
    {
        abort_unless(in_array($service, PlatformServicesMonitor::SERVICES, true), 404);

        $result = $monitor->restart($service);
        $audit->record($request, 'platform-services.restart', null, [], [
            'service' => $service,
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function renewSsl(Request $request, PlatformServicesMonitor $monitor, AuditLogger $audit): JsonResponse
    {
        $result = $monitor->renewSsl();
        $audit->record($request, 'platform-services.ssl.renew', null, [], [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'domain' => $result['ssl']['domain'] ?? null,
        ]);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
