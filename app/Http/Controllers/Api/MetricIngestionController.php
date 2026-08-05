<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerMetricResource;
use App\Jobs\Monitoring\AutoHealServicesJob;
use App\Jobs\Monitoring\EvaluateMetricAlertsJob;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MetricIngestionController extends Controller
{
    public function __invoke(Request $request, Server $server): JsonResponse|ServerMetricResource
    {
        if (! $server->monitoring_enabled || blank($server->monitoring_secret)) {
            return response()->json(['message' => 'Monitoring is disabled.'], 403);
        }

        $timestamp = $request->header('X-Monitoring-Timestamp');
        $nonce = $request->header('X-Monitoring-Nonce');
        $signature = $request->header('X-Monitoring-Signature');

        if (! ctype_digit((string) $timestamp) || abs(now()->timestamp - (int) $timestamp) > 300 || ! preg_match('/^[a-f0-9]{32,64}$/', (string) $nonce) || ! preg_match('/^[a-f0-9]{64}$/', (string) $signature)) {
            return response()->json(['message' => 'Invalid monitoring signature.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$request->getContent(), $server->monitoring_secret);
        if (! hash_equals($expected, (string) $signature)) {
            return response()->json(['message' => 'Invalid monitoring signature.'], 401);
        }

        if (! Cache::add('monitoring-nonce:'.$server->id.':'.$nonce, true, now()->addMinutes(10))) {
            return response()->json(['message' => 'Metric payload has already been accepted.'], 409);
        }

        $data = $request->validate([
            'cpu_percent' => ['required', 'numeric', 'between:0,100'],
            'memory_percent' => ['required', 'numeric', 'between:0,100'],
            'disk_percent' => ['required', 'numeric', 'between:0,100'],
            'load_average' => ['required', 'numeric', 'between:0,100000'],
            'memory_used_bytes' => ['nullable', 'integer', 'min:0'],
            'memory_total_bytes' => ['nullable', 'integer', 'min:0'],
            'disk_used_bytes' => ['nullable', 'integer', 'min:0'],
            'disk_total_bytes' => ['nullable', 'integer', 'min:0'],
            'network_rx_bytes' => ['nullable', 'integer', 'min:0'],
            'network_tx_bytes' => ['nullable', 'integer', 'min:0'],
            'services' => ['nullable', 'array', 'max:20'],
            'services.*' => ['boolean'],
            'processes' => ['nullable', 'array', 'max:20'],
            'processes.*.name' => ['required_with:processes', 'string', 'max:100'],
            'processes.*.cpu' => ['nullable', 'numeric', 'min:0'],
            'processes.*.memory' => ['nullable', 'numeric', 'min:0'],
        ]);

        $metric = $server->metrics()->create([...$data, 'recorded_at' => now()]);
        $server->update(['last_seen_at' => now()]);
        EvaluateMetricAlertsJob::dispatch($metric->id)->onQueue('monitoring');
        AutoHealServicesJob::dispatch($metric->id)->onQueue('monitoring');

        return (new ServerMetricResource($metric))->response()->setStatusCode(202);
    }
}
