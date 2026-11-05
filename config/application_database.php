<?php

return [
    'bootstrap' => [
        'enabled' => env('APP_DB_BOOTSTRAP', false),
        'admin_connection' => 'application_admin',
    ],

    'application_role' => 'muonline_web_application',
];
