<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Forces a test variable into every environment source Laravel can read.
 */
function forceTestingEnvironmentVariable(string $name, string $value): void
{
    $wasStored = putenv($name.'='.$value);

    if (! $wasStored) {
        throw new RuntimeException(sprintf(
            'Unable to set required test environment variable [%s].',
            $name,
        ));
    }

    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$testingEnvironment = [
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/muonline-web-laravel-phpunit-'.getmypid().'-config.php',
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'GAME_DATA_SOURCE' => 'fixture',
    'GAME_DB_CONNECTION' => 'sqlite',
    'GAME_DB_HOST' => '',
    'GAME_DB_MUONLINE' => ':memory:',
    'GAME_DB_PASSWORD' => '',
    'GAME_DB_RANKING' => ':memory:',
    'GAME_DB_USERNAME' => '',
    'GAME_WRITES_ENABLED' => 'false',
    'MAIL_MAILER' => 'array',
    'NIGHTWATCH_ENABLED' => 'false',
    'PULSE_ENABLED' => 'false',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'TELESCOPE_ENABLED' => 'false',
];

foreach ($testingEnvironment as $name => $value) {
    forceTestingEnvironmentVariable($name, $value);
}

$requiredDatabaseEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
];

foreach ($requiredDatabaseEnvironment as $name => $expectedValue) {
    $actualValue = getenv($name);

    if ($actualValue !== $expectedValue) {
        throw new RuntimeException(sprintf(
            'Refusing to start tests: %s must be [%s], received [%s].',
            $name,
            $expectedValue,
            is_string($actualValue) ? $actualValue : '<unset>',
        ));
    }
}
