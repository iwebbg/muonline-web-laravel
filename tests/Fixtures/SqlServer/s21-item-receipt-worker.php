<?php

use App\CharacterServices\CharacterServiceType;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterServiceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MarketItemEscrowRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\DatabaseManager;

/**
 * Runs one independent receipt transaction for the isolated SQL Server concurrency test.
 */
require dirname(__DIR__, 2).'/integration-bootstrap.php';

$application = require dirname(__DIR__, 3).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
$configuration = $application['config']->get('database.connections.game');

if (($configuration['driver'] ?? null) !== 'sqlsrv'
    || ($configuration['host'] ?? null) !== 'sqlserver-test'
    || ($configuration['database'] ?? null) !== 'MuOnline_S21_Test'
    || ($configuration['username'] ?? null) !== 'sa') {
    throw new RuntimeException('Refusing to run a receipt worker outside the disposable S21 SQL Server service.');
}

$input = json_decode((string) fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
$connection = $application->make(DatabaseManager::class)->connection('game');
$connection->beginTransaction();

try {
    $connection->table('MEMB_STAT')->where('memb___id', $input['accountId'])->lockForUpdate()->first();
    fwrite(STDOUT, "LOCKED\n");
    fflush(STDOUT);

    if (trim((string) fgets(STDIN)) !== 'RUN') {
        throw new RuntimeException('The receipt worker did not receive its start signal.');
    }

    usleep(200000);
    if (($input['type'] ?? 'item') === 'points') {
        $application->singleton(GameVersionProfile::class, MuDevsSeason21Profile::class);
        $result = $application->make(SqlServerMuDevsSeason21CharacterServiceRepository::class)->applyPurchaseForOperation(
            $input['accountId'], $input['characterName'], CharacterServiceType::LevelUpPointsPurchase,
            1000, null, $input['operationKey'],
        );
        $value = $result->after['points'];
    } else {
        $result = $application->make(SqlServerMuDevsSeason21MarketItemEscrowRepository::class)->depositForOperation(
            accountId: $input['accountId'],
            itemBinary: hex2bin($input['itemHex']),
            width: 1,
            height: 4,
            operationKey: $input['operationKey'],
        );
        $value = $result->value;
    }
    $connection->commit();
    fwrite(STDOUT, json_encode(['result' => $value], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    if ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }

    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
