<?php

return [
    'email_verification_required' => (bool) env('EMAIL_VERIFICATION_REQUIRED', env('APP_ENV', 'production') !== 'local'),
    'development_admin' => [
        'email' => env('DEV_SUPER_ADMIN_EMAIL', 'admin@uplary.test'),
        'password' => env('DEV_SUPER_ADMIN_PASSWORD', 'Uplary!Dev2026'),
    ],

    /*
     * How long bootstrapping waits for a new host to start answering on its SSH port.
     * A provider reports a machine "active" the moment it powers on, which is a good
     * half-minute or more before sshd is listening, so the first connection attempt is
     * a race the provision loses. Waiting is cheaper than failing a whole provision.
     */
    'ssh_ready_timeout' => (int) env('CLOUDDECK_SSH_READY_TIMEOUT', 180),

    /*
     * Providers an account can be connected for. "api" marks the ones Uplary can drive
     * directly — creating and destroying servers through their API. The rest are recorded
     * so an operator can keep their credentials and infrastructure organised here, but
     * their servers are attached with the custom-server flow, by IP over SSH. Marking that
     * honestly in one place is better than a dropdown that implies provisioning it cannot do.
     */
    'providers' => [
        'digitalocean' => ['label' => 'DigitalOcean', 'api' => true],
        'aws' => ['label' => 'AWS', 'api' => false],
        'hetzner' => ['label' => 'Hetzner', 'api' => false],
        'vultr' => ['label' => 'Vultr', 'api' => false],
        'linode' => ['label' => 'Linode', 'api' => false],
        'oracle' => ['label' => 'Oracle Cloud Infrastructure', 'api' => false],
        'upcloud' => ['label' => 'UpCloud', 'api' => false],
        'contabo' => ['label' => 'Contabo', 'api' => false],
    ],
];
