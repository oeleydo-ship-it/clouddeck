<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'automatic_tax' => env('STRIPE_AUTOMATIC_TAX', true),
    ],

    'digitalocean' => [
        'url' => env('DIGITALOCEAN_API_URL', 'https://api.digitalocean.com/v2'),
    ],

    'hetzner' => [
        'url' => env('HETZNER_API_URL', 'https://api.hetzner.cloud/v1'),
    ],

    'google' => [
        // Prefer Admin → Google Auth (system_settings). .env is the fallback for local/dev.
        'enabled' => env('GOOGLE_AUTH_ENABLED', true),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/google/callback'),
    ],

    // No key here: Cloudflare tokens belong to an account, are entered in the console, and
    // are stored encrypted per user. Only the base URL is configuration.
    'cloudflare' => [
        'url' => env('CLOUDFLARE_API_URL', 'https://api.cloudflare.com/client/v4'),
    ],

];
