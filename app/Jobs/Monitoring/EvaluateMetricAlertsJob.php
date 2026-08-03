<?php

namespace App\Jobs\Monitoring;

use App\Models\AlertIncident;
use App\Models\AlertRule;
use App\Models\ServerMetric;
use App\Notifications\AlertTriggeredNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateMetricAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $metricId)
    {
        $this->onQueue('monitoring');
    }

    public function handle(): void
    {
        $metric = ServerMetric::with('server')->findOrFail($this->metricId);
        AlertRule::where('server_id', $metric->server_id)->where('enabled', true)->where('metric', '!=', 'server_offline')->each(function (AlertRule $rule) use ($metric): void {
            $value = (float) $metric->{$rule->metric};
            if (! $this->matches($value, $rule->operator, (float) $rule->threshold)) {
                $this->resolve($rule);

                return;
            }

            $values = ServerMetric::where('server_id', $metric->server_id)->latest('recorded_at')->limit($rule->consecutive_samples)->pluck($rule->metric);
            if ($values->count() < $rule->consecutive_samples || $values->contains(fn ($sample) => ! $this->matches((float) $sample, $rule->operator, (float) $rule->threshold))) {
                return;
            }

            $this->trigger($rule, $value, $rule->name.' on '.$metric->server->name);
        });
    }

    public function matches(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            'gt' => $value > $threshold,
            'gte' => $value >= $threshold,
            'lt' => $value < $threshold,
            'lte' => $value <= $threshold,
        };
    }

    public function trigger(AlertRule $rule, float $value, string $message): void
    {
        $incident = AlertIncident::where('alert_rule_id', $rule->id)->where('status', 'open')->first();
        if (! $incident) {
            $incident = AlertIncident::create(['user_id' => $rule->user_id, 'server_id' => $rule->server_id, 'alert_rule_id' => $rule->id, 'status' => 'open', 'severity' => $rule->severity, 'metric' => $rule->metric, 'value' => $value, 'threshold' => $rule->threshold, 'message' => $message, 'started_at' => now()]);
        } else {
            $incident->update(['value' => $value, 'message' => $message]);
        }

        if (! $incident->last_notified_at || $incident->last_notified_at->lte(now()->subMinutes($rule->cooldown_minutes))) {
            $incident->update(['last_notified_at' => now()]);
            $rule->user->notify(new AlertTriggeredNotification($incident));
        }
    }

    private function resolve(AlertRule $rule): void
    {
        AlertIncident::where('alert_rule_id', $rule->id)->where('status', 'open')->update(['status' => 'resolved', 'resolved_at' => now()]);
    }
}
