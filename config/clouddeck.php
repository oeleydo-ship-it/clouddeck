<?php

return [
    'email_verification_required' => (bool) env('EMAIL_VERIFICATION_REQUIRED', env('APP_ENV', 'production') !== 'local'),
    'development_admin' => [
        'email' => env('DEV_SUPER_ADMIN_EMAIL', 'admin@clouddeck.test'),
        'password' => env('DEV_SUPER_ADMIN_PASSWORD', 'CloudDeck!Dev2026'),
    ],
];
