<?php

/**
 * Plan entitlement catalog.
 *
 * Quotas (how many) live on plans.limits — servers, managed_servers, sites, managed_sites,
 * databases, api_tokens, teams, team_members. They are not listed here.
 *
 * These booleans gate optional console modules (can the customer use this at all?).
 * Example: providers = allow BYOS / connect own cloud; servers limit = how many BYOS hosts.
 * Example: managed_servers = allow managed provisioning; managed_servers limit = how many.
 */
return [
    'labels' => [
        'firewall' => 'Firewall',
        'security' => 'Security detection',
        'notifications' => 'Notifications',
        'providers' => 'BYOS access (connect own cloud)',
        'managed_servers' => 'Managed server access',
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
