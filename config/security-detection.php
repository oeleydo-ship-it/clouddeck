<?php

return [
    'enabled' => env('SECURITY_DETECTION_ENABLED', true),
    'scan_interval_minutes' => 5,
    'coalesce_minutes' => 30,
    'notification_cooldown_minutes' => 30,
    'auto_block_critical_ips' => false,
    'rules' => [
        'ssh.failed_logins' => ['enabled' => true, 'threshold' => 5, 'lookback_minutes' => 5, 'severity' => 'warning', 'name' => 'Repeated failed SSH logins'],
        'privilege.admin_user_created' => ['enabled' => true, 'threshold' => 1, 'lookback_minutes' => 10, 'severity' => 'critical', 'name' => 'Administrative user created'],
        'process.crypto_miner' => ['enabled' => true, 'threshold' => 1, 'cpu_threshold' => 80, 'lookback_minutes' => 5, 'severity' => 'critical', 'name' => 'Possible crypto-mining process'],
        'network.suspicious_outbound' => ['enabled' => true, 'threshold' => 1, 'ports' => [3333, 4444, 5555, 7777, 14444], 'lookback_minutes' => 5, 'severity' => 'critical', 'name' => 'Suspicious mining-related outbound connection'],
        'integrity.critical_file_changed' => ['enabled' => true, 'threshold' => 1, 'lookback_minutes' => 5, 'severity' => 'critical', 'name' => 'Critical file changed'],
        'web.bruteforce' => ['enabled' => true, 'threshold' => 10, 'lookback_minutes' => 5, 'severity' => 'warning', 'name' => 'Web login brute force'],
        'web.post_burst' => ['enabled' => true, 'threshold' => 60, 'lookback_minutes' => 1, 'severity' => 'warning', 'name' => 'Suspicious POST burst'],
        'web.route_scan' => ['enabled' => true, 'threshold' => 40, 'lookback_minutes' => 2, 'severity' => 'warning', 'name' => 'Rapid route scanning'],
        'web.bad_user_agent' => ['enabled' => true, 'threshold' => 20, 'lookback_minutes' => 5, 'severity' => 'warning', 'name' => 'Known scanner user agent'],
        'waf.blocked' => ['enabled' => true, 'threshold' => 10, 'lookback_minutes' => 5, 'severity' => 'warning', 'name' => 'Repeated WAF blocks'],
        'malware.signature' => ['enabled' => true, 'threshold' => 1, 'lookback_minutes' => 5, 'severity' => 'critical', 'name' => 'Malware signature detected'],
        'app.unexpected_admin_action' => ['enabled' => true, 'threshold' => 1, 'lookback_minutes' => 5, 'severity' => 'warning', 'name' => 'Unexpected administrative action'],
    ],
    'bad_user_agents' => ['sqlmap', 'nikto', 'masscan', 'nmap', 'acunetix', 'nessus', 'zgrab'],
    'miner_processes' => ['xmrig', 'minerd', 'cpuminer', 'ethminer', 'kdevtmpfsi', 'kinsing'],
];
