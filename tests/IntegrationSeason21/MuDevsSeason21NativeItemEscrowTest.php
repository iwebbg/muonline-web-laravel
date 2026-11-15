<?php

namespace Tests\IntegrationSeason21;

use App\CharacterServices\CharacterServiceType;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterServiceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21ItemSerialRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MarketItemEscrowRepository;
use App\Game\Versions\MuDevsSeason21\CharacterServiceJournalMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Market\MarketEscrowResult;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Tests\Integration\TestCase;

/**
 * Verifies native S21 custody writes only inside the disposable SQL Server service.
 */
final class MuDevsSeason21NativeItemEscrowTest extends TestCase
{
    private const FLAMEBERGE = '1A00FF01020306000000FFFEFEFEFFFFFFFFFFFFFFFFFFFFFF';

    /**
     * Preserves exact binary width, reserved bytes and idempotency through SQL Server.
     */
    public function test_extract_and_deposit_preserve_the_native_sql_server_blob(): void
    {
        $original = $this->seedWarehouse('s21escrow');
        $repository = $this->app->make(SqlServerMuDevsSeason21MarketItemEscrowRepository::class);
        $item = hex2bin(self::FLAMEBERGE);

        self::assertSame(MarketEscrowResult::Extracted, $repository->extractForOperation('s21escrow', 0, 0, $item, operationKey: __FUNCTION__));
        self::assertSame(substr_replace($original, str_repeat("\xFF", 25), 0, 25), $this->binary('s21escrow'));
        self::assertSame(MarketEscrowResult::AlreadyExtracted, $repository->extractForOperation('s21escrow', 0, 0, $item, operationKey: __FUNCTION__));
        self::assertSame(MarketEscrowResult::Deposited, $repository->depositForOperation('s21escrow', $item, 1, 4, operationKey: __FUNCTION__));
        self::assertSame($original, $this->binary('s21escrow'));
        self::assertSame(MarketEscrowResult::AlreadyDeposited, $repository->depositForOperation('s21escrow', $item, 1, 4, operationKey: __FUNCTION__));
        self::assertSame(6000, (int) $this->game()->table('warehouse')->where('AccountID', 's21escrow')
            ->selectRaw('DATALENGTH(Items) AS byte_count')->value('byte_count'));
    }

    /**
     * Restores all 6000 bytes when the outer operation rolls back after extraction.
     */
    public function test_sql_server_rollback_restores_the_exact_original_item(): void
    {
        $original = $this->seedWarehouse('s21roll');
        $connection = $this->game();
        $connection->beginTransaction();

        try {
            $this->app->make(SqlServerMuDevsSeason21MarketItemEscrowRepository::class)
                ->extractForOperation('s21roll', 0, 0, hex2bin(self::FLAMEBERGE), operationKey: __FUNCTION__);
            self::assertNotSame($original, $this->binary('s21roll'));
        } finally {
            $connection->rollBack();
        }

        self::assertSame($original, $this->binary('s21roll'));
    }

    /**
     * Creates the native receipt extension once and preserves records on repeated installation.
     */
    public function test_receipt_schema_extension_is_idempotent(): void
    {
        $connection = $this->game();
        $connection->beginTransaction();

        try {
            $connection->statement('DROP TABLE IWEB_S21_ItemOperation');
            $connection->statement('DROP TABLE IWEB_S21_CharacterOperation');
            $sql = file_get_contents(resource_path('game/mudevs_season_21/schema-extensions.sql'));
            $connection->unprepared($sql);
            $connection->table('IWEB_S21_ItemOperation')->insert([
                'OperationKey' => 'schema-check', 'Direction' => 'warehouse-extract',
                'AccountID' => 'owner', 'PayloadHash' => str_repeat('a', 64),
            ]);
            $connection->table('IWEB_S21_CharacterOperation')->insert([
                'OperationKey' => 'paid-schema-check', 'AccountID' => 'owner', 'PayloadHash' => str_repeat('b', 64),
                'ResultJson' => '{"before":{"points":100},"after":{"points":200}}',
            ]);
            $connection->unprepared($sql);

            self::assertSame(1, $connection->table('IWEB_S21_CharacterOperation')->count());
            self::assertSame(200, json_decode($connection->table('IWEB_S21_CharacterOperation')->value('ResultJson'), true)['after']['points']);
            self::assertSame(1, $connection->table('IWEB_S21_ItemOperation')->count());
            self::assertNotNull($connection->table('IWEB_S21_ItemOperation')->value('AppliedAt'));
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * Rolls back native extraction if the runtime principal cannot insert its receipt.
     */
    public function test_receipt_insert_permission_failure_rolls_back_native_extraction(): void
    {
        $original = $this->seedWarehouse('s21denied');
        $connection = $this->game();
        $connection->statement('CREATE USER [S21ReceiptDenied] WITHOUT LOGIN');
        $connection->statement('GRANT SELECT ON dbo.MEMB_STAT TO [S21ReceiptDenied]');
        $connection->statement('GRANT SELECT, UPDATE ON dbo.warehouse TO [S21ReceiptDenied]');
        $connection->statement('GRANT SELECT ON dbo.IWEB_S21_ItemOperation TO [S21ReceiptDenied]');

        try {
            $connection->statement("EXECUTE AS USER = 'S21ReceiptDenied'");

            try {
                $this->app->make(SqlServerMuDevsSeason21MarketItemEscrowRepository::class)
                    ->extractForOperation('s21denied', 0, 0, hex2bin(self::FLAMEBERGE), __FUNCTION__);
                self::fail('Receipt INSERT permission failure must abort native extraction.');
            } catch (QueryException $exception) {
                self::assertSame(229, (int) ($exception->errorInfo[1] ?? 0));
                self::assertStringContainsString('INSERT', $exception->getMessage());
            } finally {
                $connection->statement('REVERT');
            }

            self::assertSame($original, $this->binary('s21denied'));
            self::assertSame(0, $connection->table('IWEB_S21_ItemOperation')->where('OperationKey', __FUNCTION__)->count());
        } finally {
            $connection->statement('DROP USER [S21ReceiptDenied]');
        }
    }

    /**
     * Rolls back the character counter when native paid receipt INSERT permission is absent.
     */
    public function test_paid_receipt_permission_failure_rolls_back_the_native_counter(): void
    {
        $this->seedWarehouse('s21pdeny');
        $connection = $this->game();
        $connection->table('Character')->insert([
            'AccountID' => 's21pdeny', 'Name' => 'PaidDenied', 'CharacterIndex' => 9943,
            'Class' => 68, 'cLevel' => 400, 'ResetCount' => 2, 'LevelUpPoint' => 100,
        ]);
        $connection->statement('CREATE USER [S21PaidDenied] WITHOUT LOGIN');
        $connection->statement('GRANT SELECT ON dbo.MEMB_STAT TO [S21PaidDenied]');
        $connection->statement('GRANT SELECT, UPDATE ON dbo.Character TO [S21PaidDenied]');
        $connection->statement('GRANT SELECT ON dbo.IWEB_S21_CharacterOperation TO [S21PaidDenied]');

        try {
            $connection->statement("EXECUTE AS USER = 'S21PaidDenied'");
            try {
                $this->paidRepository()->applyPurchaseForOperation('s21pdeny', 'PaidDenied', CharacterServiceType::LevelUpPointsPurchase, 1000, null, 'sql-paid-denied');
                self::fail('Missing receipt permission must abort the counter change.');
            } catch (QueryException $exception) {
                self::assertSame(229, (int) ($exception->errorInfo[1] ?? 0));
                self::assertStringContainsString('INSERT', $exception->getMessage());
            } finally {
                $connection->statement('REVERT');
            }
            self::assertSame(100, (int) $connection->table('Character')->where('Name', 'PaidDenied')->value('LevelUpPoint'));
            self::assertSame(0, $connection->table('IWEB_S21_CharacterOperation')->where('OperationKey', 'sql-paid-denied')->count());
        } finally {
            $connection->statement('DROP USER [S21PaidDenied]');
            $connection->table('Character')->where('AccountID', 's21pdeny')->delete();
            $connection->table('warehouse')->where('AccountID', 's21pdeny')->delete();
            $connection->table('MEMB_STAT')->where('memb___id', 's21pdeny')->delete();
        }
    }

    /**
     * Serializes two independent SQL Server sessions attempting the same native delivery.
     */
    public function test_concurrent_delivery_commits_one_item_and_one_receipt(): void
    {
        $original = $this->seedWarehouse('s21race');
        $empty = substr_replace($original, str_repeat("\xFF", 25), 0, 25);
        $this->game()->statement('UPDATE warehouse SET Items = CONVERT(varbinary(6000), ?, 2) WHERE AccountID = ?', [strtoupper(bin2hex($empty)), 's21race']);
        $process = proc_open(
            [PHP_BINARY, base_path('tests/Fixtures/SqlServer/s21-item-receipt-worker.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 10);

        try {
            fwrite($pipes[0], json_encode(['accountId' => 's21race', 'operationKey' => 'sql-race-once', 'itemHex' => self::FLAMEBERGE], JSON_THROW_ON_ERROR)."\n");
            fflush($pipes[0]);
            self::assertSame("LOCKED\n", fgets($pipes[1]));
            fwrite($pipes[0], "RUN\n");
            fflush($pipes[0]);
            $result = $this->app->make(SqlServerMuDevsSeason21MarketItemEscrowRepository::class)
                ->depositForOperation('s21race', hex2bin(self::FLAMEBERGE), 1, 4, 'sql-race-once');
            $childResult = json_decode((string) fgets($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
            fclose($pipes[0]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            $process = null;

            self::assertSame(0, $exitCode, $error);
            self::assertSame(MarketEscrowResult::AlreadyDeposited, $result);
            self::assertSame(MarketEscrowResult::Deposited->value, $childResult['result']);
            self::assertSame($original, $this->binary('s21race'));
            self::assertSame(1, $this->game()->table('IWEB_S21_ItemOperation')->where('OperationKey', 'sql-race-once')->count());
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }

    /**
     * Applies one point purchase once when independent SQL Server sessions share its operation identity.
     */
    public function test_concurrent_paid_points_commit_one_counter_change_and_one_receipt(): void
    {
        $this->seedWarehouse('s21paid');
        $this->game()->table('Character')->insert([
            'AccountID' => 's21paid', 'Name' => 'PaidHero', 'CharacterIndex' => 9942,
            'Class' => 68, 'cLevel' => 400, 'ResetCount' => 2, 'LevelUpPoint' => 100,
        ]);
        $process = proc_open(
            [PHP_BINARY, base_path('tests/Fixtures/SqlServer/s21-item-receipt-worker.php')],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 10);

        try {
            fwrite($pipes[0], json_encode(['accountId' => 's21paid', 'characterName' => 'PaidHero', 'type' => 'points', 'operationKey' => 'sql-paid-race'], JSON_THROW_ON_ERROR)."\n");
            fflush($pipes[0]);
            self::assertSame("LOCKED\n", fgets($pipes[1]));
            fwrite($pipes[0], "RUN\n");
            fflush($pipes[0]);
            $result = $this->paidRepository()->applyPurchaseForOperation('s21paid', 'PaidHero', CharacterServiceType::LevelUpPointsPurchase, 1000, null, 'sql-paid-race');
            $childResult = json_decode((string) fgets($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
            fclose($pipes[0]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            $process = null;

            self::assertSame(0, $exitCode, $error);
            self::assertSame(1100, $result->after['points']);
            self::assertSame(1100, $childResult['result']);
            self::assertSame(1100, (int) $this->game()->table('Character')->where('Name', 'PaidHero')->value('LevelUpPoint'));
            self::assertSame(1, $this->game()->table('IWEB_S21_CharacterOperation')->where('OperationKey', 'sql-paid-race')->count());
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            $this->game()->table('IWEB_S21_CharacterOperation')->where('AccountID', 's21paid')->delete();
            $this->game()->table('Character')->where('AccountID', 's21paid')->delete();
            $this->game()->table('warehouse')->where('AccountID', 's21paid')->delete();
            $this->game()->table('MEMB_STAT')->where('memb___id', 's21paid')->delete();
        }
    }

    /**
     * Reserves serials through the supplied server procedure without touching a live counter.
     */
    public function test_native_serial_repository_advances_the_authoritative_counter(): void
    {
        $connection = $this->game();
        $connection->statement('CREATE TABLE GameServerInfo (Number INT NOT NULL, ItemCount INT NOT NULL, ZenCount INT NULL, AceItemCount INT NULL)');
        $connection->unprepared('CREATE PROCEDURE dbo.WZ_GetItemSerial AS BEGIN DECLARE @ItemSerial INT; SET NOCOUNT ON; BEGIN TRANSACTION; UPDATE GameServerInfo SET @ItemSerial = ItemCount = ItemCount + 1; IF (@@ERROR <> 0) BEGIN ROLLBACK TRANSACTION; SELECT -1; END ELSE BEGIN COMMIT TRANSACTION; SELECT @ItemSerial; END END');

        try {
            $connection->table('GameServerInfo')->insert(['Number' => 0, 'ItemCount' => 100]);
            $repository = $this->app->make(SqlServerMuDevsSeason21ItemSerialRepository::class);

            self::assertSame(101, $repository->nextSerial());
            self::assertSame(102, $repository->nextSerial());
            self::assertSame(102, (int) $connection->table('GameServerInfo')->value('ItemCount'));
        } finally {
            $connection->statement('DROP PROCEDURE dbo.WZ_GetItemSerial');
            $connection->statement('DROP TABLE GameServerInfo');
        }
    }

    /**
     * Seeds explicit identities and native binary values without text transcoding.
     */
    private function seedWarehouse(string $accountId): string
    {
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin(self::FLAMEBERGE), 0, 25);
        $binary = substr_replace($binary, str_repeat("\xA5", 25), 130 * 25, 25);
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => $accountId, 'ConnectStat' => 0]);
        $this->game()->statement(
            'INSERT INTO warehouse (AccountID, AccountIndex, Items, Money) VALUES (?, ?, CONVERT(varbinary(6000), ?, 2), 0)',
            [$accountId, 1, strtoupper(bin2hex($binary))],
        );

        return $binary;
    }

    /**
     * Constructs the native paid boundary independently of the default legacy test profile.
     */
    private function paidRepository(): SqlServerMuDevsSeason21CharacterServiceRepository
    {
        return new SqlServerMuDevsSeason21CharacterServiceRepository(
            $this->app->make(DatabaseManager::class), new MuDevsSeason21Profile,
            new CharacterServiceJournalMuDevsSeason21,
        );
    }

    /**
     * Reads native varbinary as hex to make its exact representation explicit.
     */
    private function binary(string $accountId): string
    {
        $hex = $this->game()->table('warehouse')->where('AccountID', $accountId)
            ->selectRaw('CONVERT(varchar(max), Items, 2) AS item_hex')->value('item_hex');

        return hex2bin($hex);
    }

    /**
     * Returns the SQL Server connection protected by the integration base test.
     */
    private function game(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
