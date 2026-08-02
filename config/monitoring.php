<?php

return [
    'metric_retention_days' => (int) env('MONITORING_RETENTION_DAYS', 30),
    'incident_retention_days' => (int) env('MONITORING_INCIDENT_RETENTION_DAYS', 180),
];
