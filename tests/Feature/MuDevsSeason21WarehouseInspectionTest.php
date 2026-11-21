<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuDevsSeason21WarehouseInspectionRepository;
use App\Game\Versions\MuDevsSeason21\ItemCodecMuDevsSeason21;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies account-scoped S21 diagnostics without enabling any game writes.
 */
final class MuDevsSeason21WarehouseInspectionTest extends TestCase
{
    /**
     * Reads all native positions for one owner and leaves stored bytes unchanged.
     */
    public function test_reads_only_the_requested_account_and_preserves_the_full_blob(): void
    {
        $connection = $this->createGameSchema();
        $binary = $this->helmetBlob();
        $connection->table('warehouse')->insert([
            ['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary, 'Money' => 0],
            ['AccountID' => 'other', 'AccountIndex' => 2, 'Items' => str_repeat("\xFF", 6000), 'Money' => 0],
        ]);

        $records = app(SqlServerMuDevsSeason21WarehouseInspectionRepository::class)->forAccount('owner');

        $this->assertNotNull($records);
        $this->assertCount(240, $records);
        $this->assertSame('Brilliant Knight Helm [S]', $records[0]->metadata?->name);
        $this->assertSame('7:270', $records[0]->code());
        $this->assertSame($binary, app(ItemCodecMuDevsSeason21::class)->encode($records));
        $this->assertSame($binary, $connection->table('warehouse')->where('AccountID', 'owner')->value('Items'));
        $this->assertNull(app(SqlServerMuDevsSeason21WarehouseInspectionRepository::class)->forAccount('missing'));
    }

    /**
     * Resolves an exact native extended-vault number while retaining owner isolation.
     */
    public function test_reads_the_requested_extended_vault_without_crossing_account_boundaries(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('ExtWarehouse')->insert([
            ['AccountID' => 'owner', 'AccountIndex' => 1, 'Number' => 2, 'Items' => $this->helmetBlob(), 'Money' => 0],
            ['AccountID' => 'other', 'AccountIndex' => 2, 'Number' => 1, 'Items' => $this->helmetBlob(), 'Money' => 0],
        ]);
        $inspector = app(SqlServerMuDevsSeason21WarehouseInspectionRepository::class);

        $records = $inspector->forAccount('owner', 2);

        $this->assertNotNull($records);
        $this->assertSame('7:270', $records[0]->code());
        $this->assertNull($inspector->forAccount('owner', 1));
    }

    /**
     * Prints occupied diagnostic records while keeping the account identifier out of JSON.
     */
    public function test_command_prints_native_item_metadata_as_json(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('warehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $this->helmetBlob(), 'Money' => 0,
        ]);

        $this->artisan('game:inspect-s21-warehouse', ['account' => 'owner', '--json' => true])
            ->expectsOutputToContain('"vault":0,"storedSlots":240,"items":[{"slot":0,"code":"7:270"')
            ->assertSuccessful();
    }

    /**
     * Reports an absent vault without substituting an empty or another owner's vault.
     */
    public function test_command_fails_for_a_missing_vault(): void
    {
        $this->createGameSchema();

        $this->artisan('game:inspect-s21-warehouse', ['account' => 'missing'])
            ->expectsOutput('No matching S21 warehouse exists for that account and vault.')
            ->assertFailed();
    }

    /**
     * Rejects invalid native vault input before querying a warehouse table.
     */
    public function test_command_rejects_a_non_numeric_vault_number(): void
    {
        $this->artisan('game:inspect-s21-warehouse', ['account' => 'owner', '--vault' => 'bad'])
            ->expectsOutput('Supply an account identifier of 1–10 bytes and a non-negative native vault number.')
            ->assertFailed();
    }

    /**
     * Refuses malformed native storage instead of silently padding or truncating bytes.
     */
    public function test_command_reports_an_invalid_storage_length(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('warehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => str_repeat("\xFF", 16), 'Money' => 0,
        ]);

        $this->artisan('game:inspect-s21-warehouse', ['account' => 'owner'])
            ->expectsOutput('The MUDevs S21 blob length does not match its expected 25-byte records.')
            ->assertFailed();
    }

    /**
     * Loads the sanitized projection into the isolated in-memory game connection.
     */
    private function createGameSchema(): ConnectionInterface
    {
        $connection = app(DatabaseManager::class)->connection('game');
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));

        return $connection;
    }

    /**
     * Returns a sanitized client-matched helmet in the first of 240 native positions.
     */
    private function helmetBlob(): string
    {
        return hex2bin('0E7C010102030180007000FFFFFFFFFFFFFFFFFFFFFFFFFFFF').str_repeat("\xFF", 5975);
    }
}
