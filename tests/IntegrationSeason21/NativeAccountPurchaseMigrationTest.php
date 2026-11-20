<?php

namespace Tests\IntegrationSeason21;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Integration\TestCase;

/**
 * Verifies native purchase storage and financial rollback protection on disposable SQL Server.
 */
final class NativeAccountPurchaseMigrationTest extends TestCase
{
    /**
     * Creates seeded native rates without modifying legacy plans and refuses removal of financial history.
     */
    public function test_native_purchase_migration_preserves_legacy_plans_and_refuses_lossy_rollback(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $defaultConnection = $database->getDefaultConnection();
        $originalSchema = Schema::getFacadeRoot();
        $connection = $database->connection('game');
        $database->setDefaultConnection('game');
        Schema::swap($connection->getSchemaBuilder());
        try {
            foreach (['administrators', 'wallet_transactions', 'vip_plans'] as $name) {
                Schema::create($name, function (Blueprint $table): void {
                    $table->id();
                });
            }
            $connection->statement('INSERT INTO vip_plans DEFAULT VALUES');
            $migration = require database_path('migrations/2026_11_15_095510_create_native_account_purchase_tables.php');
            $migration->up();
            self::assertSame(5, $connection->table('game_currency_exchange_rates')->count());
            self::assertNull($connection->table('vip_plans')->value('native_account_level'));
            $rateId = $connection->table('game_currency_exchange_rates')->where('currency', 'ruud')->value('id');
            $connection->table('game_currency_exchange_rate_revisions')->insert([
                'game_currency_exchange_rate_id' => $rateId, 'revision' => 2,
                'before_snapshot' => '{"coin_units":1}', 'snapshot' => '{"coin_units":2}', 'created_at' => now(),
            ]);
            try {
                $migration->down();
                self::fail('Operator and financial history must survive rollback.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('forward migration', $exception->getMessage());
                self::assertSame(1, $connection->table('game_currency_exchange_rate_revisions')->count());
            }
            $connection->table('game_currency_exchange_rate_revisions')->delete();
            $migration->down();
            self::assertFalse(Schema::hasTable('native_account_purchases'));
            self::assertFalse(Schema::hasColumn('vip_plans', 'native_account_level'));
            self::assertSame(1, $connection->table('vip_plans')->count());
        } finally {
            foreach (['native_account_purchases', 'game_currency_exchange_rate_revisions', 'game_currency_exchange_rates', 'vip_plans', 'wallet_transactions', 'administrators'] as $name) {
                Schema::dropIfExists($name);
            }
            $database->setDefaultConnection($defaultConnection);
            Schema::swap($originalSchema);
        }
    }
}
