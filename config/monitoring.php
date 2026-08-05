<?php

return [
    'metric_retention_days' => (int) env('MONITORING_RETENTION_DAYS', 30),
    'incident_retention_days' => (int) env('MONITORING_INCIDENT_RETENTION_DAYS', 180),
    'auto_heal_cooldown_minutes' => (int) env('MONITORING_AUTO_HEAL_COOLDOWN', 15),
    'auto_heal_consecutive_samples' => (int) env('MONITORING_AUTO_HEAL_SAMPLES', 2),
    'site_probe_timeout' => (int) env('MONITORING_SITE_PROBE_TIMEOUT', 10),
];
