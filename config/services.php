<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'recaptcha' => [
        'enabled' => filter_var(env('RECAPTCHA_ENABLED', false), FILTER_VALIDATE_BOOL),
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'minimum_score' => max(0.0, min(1.0, (float) env('RECAPTCHA_MINIMUM_SCORE', 0.5))),
        'hostname' => env('RECAPTCHA_HOSTNAME'),
        'verify_url' => env('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
        'timeout_seconds' => max(1, (int) env('RECAPTCHA_TIMEOUT_SECONDS', 5)),
    ],

    'payments' => [
        'currency' => strtoupper(env('PAYMENTS_CURRENCY', 'EUR')),
        'stripe' => [
            'enabled' => filter_var(env('STRIPE_ENABLED', false), FILTER_VALIDATE_BOOL),
            'secret' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
        ],
        'paypal' => [
            'enabled' => filter_var(env('PAYPAL_ENABLED', false), FILTER_VALIDATE_BOOL),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            'base_url' => env('PAYPAL_MODE', 'sandbox') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com',
        ],
        'revolut' => [
            'enabled' => filter_var(env('REVOLUT_ENABLED', false), FILTER_VALIDATE_BOOL),
            'secret' => env('REVOLUT_SECRET_KEY'),
            'webhook_secret' => env('REVOLUT_WEBHOOK_SECRET'),
            'api_version' => env('REVOLUT_API_VERSION', '2026-08-17'),
            'base_url' => env('REVOLUT_MODE', 'sandbox') === 'live' ? 'https://merchant.revolut.com' : 'https://sandbox-merchant.revolut.com',
        ],
    ],

];
