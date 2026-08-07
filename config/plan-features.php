<?php

/**
 * Plan entitlement catalog. Resource counts (servers, sites, databases, …) live on
 * plans.limits only. These booleans gate optional console modules and site capabilities.
 */
return [
    'labels' => [
        'firewall' => 'Firewall',
        'security' => 'Security detection',
        'notifications' => 'Notifications',
        'providers' => 'Cloud providers',
        'dns' => 'DNS',
        'ssh' => 'SSH keys',
        'monitoring' => 'Monitoring and alerts',
        'remote_management' => 'Remote management',
        'teams' => 'Team collaboration',
        'staging' => 'Staging sites',
        'backups' => 'Backups',
        'horizon' => 'Laravel Horizon',
        'reverb' => 'Laravel Reverb',
        'redis' => 'Queue workers (Redis)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default feature maps by seeded plan slug
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'free' => [
            'firewall' => false,
            'security' => false,
            'notifications' => true,
            'providers' => true,
            'dns' => false,
            'ssh' => true,
            'monitoring' => true,
            'remote_management' => false,
            'teams' => true,
            'staging' => false,
            'backups' => false,
            'horizon' => false,
            'reverb' => false,
            'redis' => false,
        ],
        'pro' => [
            'firewall' => true,
            'security' => true,
            'notifications' => true,
            'providers' => true,
            'dns' => true,
            'ssh' => true,
            'monitoring' => true,
            'remote_management' => true,
            'teams' => true,
            'staging' => true,
            'backups' => true,
            'horizon' => true,
            'reverb' => true,
            'redis' => true,
        ],
        'business' => [
            'firewall' => true,
            'security' => true,
            'notifications' => true,
            'providers' => true,
            'dns' => true,
            'ssh' => true,
            'monitoring' => true,
            'remote_management' => true,
            'teams' => true,
            'staging' => true,
            'backups' => true,
            'horizon' => true,
            'reverb' => true,
            'redis' => true,
        ],
    ],
];
