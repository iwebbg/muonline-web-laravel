<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Forces one integration variable into every environment source Laravel reads.
 */
function forceIntegrationEnvironmentVariable(string $name, string $value): void
{
    $wasStored = putenv($name.'='.$value);

    if (! $wasStored) {
        throw new RuntimeException(sprintf(
            'Unable to set required integration environment variable [%s].',
            $name,
        ));
    }

    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$integrationEnvironment = [
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/muonline-web-laravel-integration-'.getmypid().'-config.php',
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'GAME_DATA_SOURCE' => 'database',
    'GAME_DB_CONNECTION' => 'sqlsrv',
    'GAME_DB_ENCRYPT' => 'no',
    'GAME_DB_HOST' => 'sqlserver-test',
    'GAME_DB_MUONLINE' => 'MuOnline',
    'GAME_DB_PASSWORD' => 'Integration-only_Password123',
    'GAME_DB_PORT' => '1433',
    'GAME_DB_RANKING' => 'MuOnline',
    'GAME_DB_TRUST_SERVER_CERTIFICATE' => 'true',
    'GAME_DB_USERNAME' => 'sa',
    'GAME_WRITES_ENABLED' => 'false',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($integrationEnvironment as $name => $value) {
    forceIntegrationEnvironmentVariable($name, $value);
}
