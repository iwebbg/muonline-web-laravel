<?php

return [
    'data_source' => env('GAME_DATA_SOURCE', 'fixture'),

    'writes_enabled' => filter_var(
        env('GAME_WRITES_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'account_password_scheme' => env('GAME_ACCOUNT_PASSWORD_SCHEME', 'plaintext'),

    'password_reset_ttl_minutes' => (int) env('GAME_PASSWORD_RESET_TTL_MINUTES', 60),

    'ranking_limit' => (int) env('GAME_RANKING_LIMIT', 10),

    'database_connections' => [
        'primary' => 'game',
        'rankings' => env('GAME_RANKING_CONNECTION', 'game'),
    ],

    'event_timezone' => env('GAME_EVENT_TIMEZONE', 'Europe/Sofia'),

    'content_timezone' => env(
        'GAME_CONTENT_TIMEZONE',
        env('GAME_EVENT_TIMEZONE', 'Europe/Sofia'),
    ),

    'event_profile' => env('GAME_EVENT_PROFILE', 'darksteam_97d99i'),

    'event_home_limit' => (int) env('GAME_EVENT_HOME_LIMIT', 3),

    'market_listing_duration_days' => max(
        1,
        (int) env('GAME_MARKET_LISTING_DURATION_DAYS', 30),
    ),

    'server_status' => [
        'host' => env('GAME_SERVER_STATUS_HOST', env('GAME_DB_HOST', '127.0.0.1')),
        'port' => (int) env('GAME_SERVER_STATUS_PORT', 55901),
        'timeout_seconds' => (float) env('GAME_SERVER_STATUS_TIMEOUT_SECONDS', 0.5),
        'cache_seconds' => (int) env('GAME_SERVER_STATUS_CACHE_SECONDS', 10),
    ],

    'profile' => [
        'key' => env('GAME_SERVER_PROFILE', 'darksteam_97d99i'),
        'name' => 'MuOnline 97d+99i',
        'version' => '97d + 99i',
        'experience' => '9999x',
        'drop' => '60%',
        'capacity' => 500,
    ],
];
