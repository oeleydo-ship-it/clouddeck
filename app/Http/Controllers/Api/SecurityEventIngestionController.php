<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\SecurityDetectorEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SecurityEventIngestionController extends Controller
{
    private const EVENT_MAP = [
        'auth_failed' => 'web.bruteforce',
        'admin_action' => 'app.unexpected_admin_action',
        'waf_block' => 'waf.blocked',
        'malware_signature' => 'malware.signature',
        'file_changed' => 'integrity.critical_file_changed',
    ];

    public function __invoke(Request $request, Server $server, SecurityDetectorEngine $detector): JsonResponse
    {
        if (! $server->monitoring_enabled || blank($server->monitoring_secret)) {
            return response()->json(['message' => 'Monitoring is disabled.'], 403);
        }

        $timestamp = $request->header('X-Monitoring-Timestamp');
        $nonce = $request->header('X-Monitoring-Nonce');
        $signature = $request->header('X-Monitoring-Signature');
        $validEnvelope = ctype_digit((string) $timestamp)
            && abs(now()->timestamp - (int) $timestamp) <= 300
            && preg_match('/^[a-f0-9]{32,64}$/', (string) $nonce)
            && preg_match('/^[a-f0-9]{64}$/', (string) $signature);

        $expected = $validEnvelope
            ? hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$request->getContent(), $server->monitoring_secret)
            : '';

        if (! $validEnvelope || ! hash_equals($expected, (string) $signature)) {
            return response()->json(['message' => 'Invalid monitoring signature.'], 401);
        }

        if (! Cache::add('security-event-nonce:'.$server->id.':'.$nonce, true, now()->addMinutes(10))) {
            return response()->json(['message' => 'Security payload has already been accepted.'], 409);
        }

        $data = $request->validate([
            'events' => ['required', 'array', 'max:500'],
            'events.*.type' => ['required', Rule::in(array_keys(self::EVENT_MAP))],
            'events.*.site_id' => ['nullable', 'uuid'],
            'events.*.source_ip' => ['nullable', 'ip'],
            'events.*.count' => ['nullable', 'integer', 'between:1,100000'],
            'events.*.summary' => ['nullable', 'string', 'max:1000'],
            'events.*.evidence' => ['nullable', 'array', 'max:50'],
        ]);

        $events = collect($data['events'])->map(function (array $event): array {
            $event['detector_key'] = self::EVENT_MAP[$event['type']];
            $event['source'] = 'agent';
            unset($event['type']);

            return $event;
        })->all();

        $incidents = $detector->evaluate($server, $events);

        return response()->json(['accepted' => count($events), 'incidents' => $incidents->pluck('id')], 202);
    }
}
