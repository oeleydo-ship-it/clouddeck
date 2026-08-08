<?php

/**
 * Plan entitlement catalog. Resource counts (servers, managed_servers, sites, …) live on
 * plans.limits. These booleans gate optional console modules and site capabilities.
 *
 * servers = BYOS (customer cloud / custom SSH). managed_servers = platform-provided VMs.
 */
return [
    'labels' => [
        'firewall' => 'Firewall',
        'security' => 'Security detection',
        'notifications' => 'Notifications',
        'providers' => 'Cloud providers (BYOS)',
        'managed_servers' => 'Managed servers',
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

    'defaults' => [
        'free' => [
            'firewall' => false,
            'security' => false,
            'notifications' => true,
            'providers' => true,
            'managed_servers' => false,
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
            'managed_servers' => true,
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
            'managed_servers' => true,
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
