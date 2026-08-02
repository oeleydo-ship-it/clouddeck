<?php

namespace App\Jobs\Monitoring;

use App\Models\AlertIncident;
use App\Models\AlertRule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckOfflineServersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    public function handle(): void
    {
        $evaluator = new EvaluateMetricAlertsJob(0);
        AlertRule::with('server')->where('enabled', true)->where('metric', 'server_offline')->each(function (AlertRule $rule) use ($evaluator): void {
            $minutes = $rule->server->last_seen_at?->diffInMinutes(now()) ?? PHP_INT_MAX;
            if ($rule->server->monitoring_enabled && $evaluator->matches((float) $minutes, $rule->operator, (float) $rule->threshold)) {
                $evaluator->trigger($rule, (float) min($minutes, 999999), $rule->name.' on '.$rule->server->name);

                return;
            }
            AlertIncident::where('alert_rule_id', $rule->id)->where('status', 'open')->update(['status' => 'resolved', 'resolved_at' => now()]);
        });
    }
}
