<?php

namespace Tests\IntegrationSeason21;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Integration\TestCase;

/**
 * Verifies native stat-limit storage upgrades against disposable SQL Server types.
 */
final class MuDevsSeason21StatLimitMigrationTest extends TestCase
{
    /**
     * Preserves saved limits, accepts 65000 and refuses a lossy smallint rollback.
     */
    public function test_native_stat_limit_migration_preserves_values_and_rollback_safety(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $defaultConnection = $database->getDefaultConnection();
        $originalSchema = Schema::getFacadeRoot();
        $connection = $database->connection('game');
        $database->setDefaultConnection('game');
        Schema::swap($connection->getSchemaBuilder());

        try {
            Schema::create('reset_profiles', function (Blueprint $table): void {
                $table->unsignedSmallInteger('maximum_stat')->default(32_767);
            });
            $connection->table('reset_profiles')->insert(['maximum_stat' => 30000]);
            $migration = require database_path('migrations/2026_11_15_014453_widen_maximum_stat_in_reset_profiles_table.php');
            $migration->up();

            self::assertSame(30000, (int) $connection->table('reset_profiles')->value('maximum_stat'));
            $connection->table('reset_profiles')->update(['maximum_stat' => 65000]);
            self::assertSame(65000, (int) $connection->table('reset_profiles')->value('maximum_stat'));

            try {
                $migration->down();
                self::fail('A rollback must not narrow a saved 65000 limit into SQL Server smallint.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('32767', $exception->getMessage());
                self::assertSame(65000, (int) $connection->table('reset_profiles')->value('maximum_stat'));
            }

            $connection->table('reset_profiles')->update(['maximum_stat' => 32767]);
            $migration->down();
            $connection->statement('INSERT INTO reset_profiles DEFAULT VALUES');
            self::assertSame([32767, 32767], array_map('intval', $connection->table('reset_profiles')->pluck('maximum_stat')->all()));
            self::assertSame('smallint', Schema::getColumnType('reset_profiles', 'maximum_stat'));
        } finally {
            Schema::dropIfExists('reset_profiles');
            $database->setDefaultConnection($defaultConnection);
            Schema::swap($originalSchema);
        }
    }
}
