<?php

use Illuminate\Support\Str;

return [
    'name' => env('HORIZON_NAME', 'CloudDeck'),
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'clouddeck'), '_').'_horizon:'),
    'middleware' => ['web', 'auth', 'verified'],
    'waits' => ['redis:default' => 60, 'redis:deployments' => 120, 'redis:provisioning' => 120, 'redis:monitoring' => 60, 'redis:billing' => 30],
    'trim' => ['recent' => 60, 'pending' => 60, 'completed' => 60, 'recent_failed' => 10080, 'failed' => 10080, 'monitored' => 10080],
    'silenced' => [],
    'silenced_tags' => [],
    'metrics' => ['trim_snapshots' => ['job' => 24, 'queue' => 24]],
    'fast_termination' => true,
    'memory_limit' => 128,
    'defaults' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'balance' => 'auto', 'autoScalingStrategy' => 'time', 'maxProcesses' => 2, 'maxTime' => 0, 'maxJobs' => 500, 'memory' => 192, 'tries' => 3, 'timeout' => 60, 'nice' => 0],
        'deployments' => ['connection' => 'redis', 'queue' => ['deployments'], 'balance' => 'simple', 'maxProcesses' => 2, 'maxTime' => 0, 'maxJobs' => 10, 'memory' => 512, 'tries' => 1, 'timeout' => 1800, 'nice' => 5],
        'provisioning' => ['connection' => 'redis', 'queue' => ['provisioning'], 'balance' => 'simple', 'maxProcesses' => 1, 'maxTime' => 0, 'maxJobs' => 10, 'memory' => 512, 'tries' => 1, 'timeout' => 1800, 'nice' => 5],
        'notifications' => ['connection' => 'redis', 'queue' => ['notifications'], 'balance' => 'auto', 'maxProcesses' => 2, 'maxTime' => 0, 'maxJobs' => 500, 'memory' => 128, 'tries' => 3, 'timeout' => 60, 'nice' => 0],
        'operations' => ['connection' => 'redis', 'queue' => ['operations'], 'balance' => 'auto', 'maxProcesses' => 2, 'maxTime' => 0, 'maxJobs' => 100, 'memory' => 256, 'tries' => 1, 'timeout' => 600, 'nice' => 3],
        'monitoring' => ['connection' => 'redis', 'queue' => ['monitoring'], 'balance' => 'auto', 'maxProcesses' => 2, 'maxTime' => 0, 'maxJobs' => 1000, 'memory' => 128, 'tries' => 3, 'timeout' => 60, 'nice' => 0],
        'billing' => ['connection' => 'redis', 'queue' => ['billing'], 'balance' => 'auto', 'maxProcesses' => 2, 'maxTime' => 0, 'maxJobs' => 500, 'memory' => 128, 'tries' => 5, 'timeout' => 60, 'nice' => 0],
    ],
    'environments' => [
        'production' => ['default' => ['maxProcesses' => 10], 'deployments' => ['maxProcesses' => 4], 'provisioning' => ['maxProcesses' => 2], 'notifications' => ['maxProcesses' => 4], 'operations' => ['maxProcesses' => 4], 'monitoring' => ['maxProcesses' => 4], 'billing' => ['maxProcesses' => 4]],
        'local' => ['default' => ['maxProcesses' => 2], 'deployments' => ['maxProcesses' => 1], 'provisioning' => ['maxProcesses' => 1], 'notifications' => ['maxProcesses' => 1], 'operations' => ['maxProcesses' => 1], 'monitoring' => ['maxProcesses' => 1], 'billing' => ['maxProcesses' => 1]],
    ],
    'watch' => ['app', 'bootstrap', 'config/**/*.php', 'database/**/*.php', 'resources/**/*.php', 'routes', 'composer.lock', 'composer.json', '.env'],
];
