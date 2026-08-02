<?php

return [
    'transfer_disk' => env('REMOTE_TRANSFER_DISK', 'local'),
    'database_backup_disk' => env('DATABASE_BACKUP_DISK', 'local'),
    'transfer_retention_hours' => (int) env('REMOTE_TRANSFER_RETENTION_HOURS', 24),
    'database_backup_retention_days' => (int) env('DATABASE_BACKUP_RETENTION_DAYS', 30),
];
