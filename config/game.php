<?php

return [
    'data_source' => env('GAME_DATA_SOURCE', 'fixture'),

    'writes_enabled' => filter_var(
        env('GAME_WRITES_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),

    'account_password_scheme' => env('GAME_ACCOUNT_PASSWORD_SCHEME', 'plaintext'),

    'ranking_limit' => (int) env('GAME_RANKING_LIMIT', 10),

    'event_timezone' => env('GAME_EVENT_TIMEZONE', 'Europe/Sofia'),

    'event_profile' => env('GAME_EVENT_PROFILE', 'darksteam_97d99i'),

    'event_home_limit' => (int) env('GAME_EVENT_HOME_LIMIT', 3),

    'profile' => [
        'name' => env('GAME_SERVER_NAME', 'MuOnline 97d+99i'),
        'version' => env('GAME_SERVER_VERSION', '97d + 99i'),
        'experience' => env('GAME_SERVER_EXPERIENCE', '9999x'),
        'drop' => env('GAME_SERVER_DROP', '60%'),
        'capacity' => (int) env('GAME_SERVER_CAPACITY', 500),
    ],
];
