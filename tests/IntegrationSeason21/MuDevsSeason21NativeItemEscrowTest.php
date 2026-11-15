<?php

namespace Tests\IntegrationSeason21;

use App\Game\Repositories\SqlServerMuDevsSeason21MarketItemEscrowRepository;
use App\Market\MarketEscrowResult;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
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

        self::assertSame(MarketEscrowResult::Extracted, $repository->extract('s21escrow', 0, 0, $item));
        self::assertSame(substr_replace($original, str_repeat("\xFF", 25), 0, 25), $this->binary('s21escrow'));
        self::assertSame(MarketEscrowResult::AlreadyExtracted, $repository->extract('s21escrow', 0, 0, $item));
        self::assertSame(MarketEscrowResult::Deposited, $repository->deposit('s21escrow', $item, 1, 4));
        self::assertSame($original, $this->binary('s21escrow'));
        self::assertSame(MarketEscrowResult::AlreadyDeposited, $repository->deposit('s21escrow', $item, 1, 4));
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
                ->extract('s21roll', 0, 0, hex2bin(self::FLAMEBERGE));
            self::assertNotSame($original, $this->binary('s21roll'));
        } finally {
            $connection->rollBack();
        }

        self::assertSame($original, $this->binary('s21roll'));
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
