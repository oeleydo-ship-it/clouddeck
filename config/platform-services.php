<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Control-plane runtime panel
    |--------------------------------------------------------------------------
    |
    | Super-admin UI for monitoring and (where safe) starting/stopping Uplary's
    | own Redis, Horizon, queue workers, and Reverb — not customer-site workers.
    |
    */

    'pid_directory' => storage_path('app/platform-services'),

    /*
    | Optional Docker container name used when Redis is provided by Docker Desktop
    | on Windows/local installs. Only this named container is started/stopped.
    */
    'redis_docker_container' => env('PLATFORM_REDIS_CONTAINER', 'uplary-redis'),

    /*
    | Queues for `php artisan queue:work` when Horizon is unavailable (e.g. Windows
    | without pcntl). Keep in sync with config/horizon.php defaults.
    */
    'queues' => [
        'default',
        'operations',
        'deployments',
        'provisioning',
        'notifications',
        'monitoring',
        'billing',
    ],

    /*
    | Control-plane HTTPS (APP_URL host). Status probes TLS; renew runs a local
    | Certbot script on Linux only — never customer-site SSL over SSH.
    */
    'ssl' => [
        'warn_days' => (int) env('PLATFORM_SSL_WARN_DAYS', 30),
        'probe_timeout' => (int) env('PLATFORM_SSL_PROBE_TIMEOUT', 5),
        'renew_script' => env('PLATFORM_SSL_RENEW_SCRIPT', resource_path('scripts/renew-platform-ssl.sh')),
        'email' => env('PLATFORM_SSL_EMAIL', env('MAIL_FROM_ADDRESS')),
        'docs_url' => env('PLATFORM_SSL_DOCS_URL'),
        // Null = auto-detect. Feature tests may force Linux/non-local renew paths.
        'treat_as_windows' => null,
        'treat_as_local_host' => null,
    ],

];
