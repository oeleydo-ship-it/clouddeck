<?php

namespace App\Services;

use App\Models\SecurityIncident;
use App\Models\Server;
use App\Models\Site;
use App\Notifications\OperationalEventNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecurityDetectorEngine
{
    public function __construct(private readonly SecurityDetectionSettings $settings) {}

    /** @return Collection<int, SecurityIncident> */
    public function evaluate(Server $server, array $events): Collection
    {
        $settings = $this->settings->forServer($server);
        if (! $settings['enabled']) {
            return collect();
        }

        return collect($events)
            ->filter(fn ($event) => is_array($event))
            ->groupBy(fn (array $event) => implode('|', [
                $event['detector_key'] ?? '',
                $event['site_id'] ?? $event['domain'] ?? '',
                $event['source_ip'] ?? '',
            ]))
            ->map(function (Collection $group) use ($server, $settings): ?SecurityIncident {
                $event = $group->last();
                $key = (string) ($event['detector_key'] ?? '');
                $rule = $settings['rules'][$key] ?? null;

                if (! is_array($rule) || ! ($rule['enabled'] ?? false)) {
                    return null;
                }

                $count = $group->sum(fn (array $row) => max(1, (int) ($row['count'] ?? 1)));
                if ($count < (int) ($rule['threshold'] ?? 1)) {
                    return null;
                }

                $site = $this->siteFor($server, $event);
                $sourceIp = filter_var($event['source_ip'] ?? null, FILTER_VALIDATE_IP) ?: null;
                $severity = $rule['severity'];
                $evidence = $this->sanitizeEvidence((array) ($event['evidence'] ?? []));
                $evidence['observed_count'] = $count;

                return $this->record(
                    server: $server,
                    site: $site,
                    detectorKey: $key,
                    ruleName: $rule['name'] ?? Str::headline($key),
                    source: (string) ($event['source'] ?? 'collector'),
                    severity: $severity,
                    sourceIp: $sourceIp,
                    title: (string) ($event['title'] ?? ($rule['name'] ?? Str::headline($key))),
                    summary: (string) ($event['summary'] ?? 'Security detector threshold was reached.'),
                    evidence: $evidence,
                    occurrences: $count,
                );
            })
            ->filter()
            ->values();
    }

    public function sanitizeEvidence(array $evidence): array
    {
        $walk = function (array $values) use (&$walk): array {
            return collect($values)->mapWithKeys(function ($value, $key) use (&$walk): array {
                $name = strtolower((string) $key);
                if (preg_match('/password|passwd|secret|token|authorization|cookie|private.?key|env.?content/', $name)) {
                    return [$key => '[REDACTED]'];
                }

                if (is_array($value)) {
                    return [$key => $walk(array_slice($value, 0, 50, true))];
                }

                return [$key => is_scalar($value) || $value === null
                    ? Str::limit(preg_replace('/(password|token|secret)=\S+/i', '$1=[REDACTED]', (string) $value), 1000)
                    : '[UNSUPPORTED]'];
            })->all();
        };

        return $walk(array_slice($evidence, 0, 50, true));
    }

    private function record(
        Server $server,
        ?Site $site,
        string $detectorKey,
        string $ruleName,
        string $source,
        string $severity,
        ?string $sourceIp,
        string $title,
        string $summary,
        array $evidence,
        int $occurrences,
    ): SecurityIncident {
        return DB::transaction(function () use ($server, $site, $detectorKey, $ruleName, $source, $severity, $sourceIp, $title, $summary, $evidence, $occurrences): SecurityIncident {
            $incident = SecurityIncident::query()
                ->where('server_id', $server->id)
                ->where('site_id', $site?->id)
                ->where('detector_key', $detectorKey)
                ->where(fn ($query) => $sourceIp ? $query->where('source_ip', $sourceIp) : $query->whereNull('source_ip'))
                ->whereIn('status', ['open', 'acknowledged'])
                ->where('last_seen_at', '>=', now()->subMinutes(config('security-detection.coalesce_minutes', 30)))
                ->lockForUpdate()
                ->first();

            $isNew = ! $incident;
            $escalated = $incident && $this->rank($severity) > $this->rank($incident->severity);

            if ($incident) {
                $incident->update([
                    'severity' => $escalated ? $severity : $incident->severity,
                    'source' => $source,
                    'title' => Str::limit($title, 255),
                    'summary' => Str::limit($summary, 4000),
                    'evidence' => $evidence,
                    'last_seen_at' => now(),
                    'occurrence_count' => $incident->occurrence_count + $occurrences,
                ]);
            } else {
                $incident = SecurityIncident::create([
                    'user_id' => $server->user_id,
                    'team_id' => $server->team_id,
                    'server_id' => $server->id,
                    'site_id' => $site?->id,
                    'detector_key' => $detectorKey,
                    'rule_name' => Str::limit($ruleName, 255),
                    'source' => Str::limit($source, 40),
                    'severity' => $severity,
                    'status' => 'open',
                    'source_ip' => $sourceIp,
                    'title' => Str::limit($title, 255),
                    'summary' => Str::limit($summary, 4000),
                    'evidence' => $evidence,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'occurrence_count' => $occurrences,
                ]);
            }

            $cooldownExpired = ! $incident->last_notified_at
                || $incident->last_notified_at->lte(now()->subMinutes(config('security-detection.notification_cooldown_minutes', 30)));

            if ($isNew || $escalated || $cooldownExpired) {
                $incident->update(['last_notified_at' => now()]);
                $server->user->notify(new OperationalEventNotification(
                    'security_incident',
                    '[Security] '.$incident->title,
                    $incident->summary,
                    route('notifications.index', ['tab' => 'incidents']),
                    $incident->severity,
                    ['security_incident_id' => $incident->id, 'server_id' => $server->id],
                ));
            }

            return $incident->fresh();
        });
    }

    private function siteFor(Server $server, array $event): ?Site
    {
        if (filled($event['site_id'] ?? null)) {
            return $server->sites()->find($event['site_id']);
        }

        if (filled($event['domain'] ?? null)) {
            return $server->sites()->where('domain', strtolower((string) $event['domain']))->first();
        }

        return null;
    }

    private function rank(string $severity): int
    {
        return ['info' => 1, 'warning' => 2, 'critical' => 3][$severity] ?? 1;
    }
}
