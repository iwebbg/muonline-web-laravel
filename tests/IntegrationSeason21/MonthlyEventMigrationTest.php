<?php

namespace Tests\IntegrationSeason21;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Integration\TestCase;

/**
 * Verifies monthly event restrictions upgrade safely on disposable SQL Server storage.
 */
final class MonthlyEventMigrationTest extends TestCase
{
    /**
     * Preserves existing entries and refuses a rollback that would turn monthly events into daily ones.
     */
    public function test_calendar_migration_retains_existing_rows_and_refuses_lossy_rollback(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $defaultConnection = $database->getDefaultConnection();
        $originalSchema = Schema::getFacadeRoot();
        $connection = $database->connection('game');
        $database->setDefaultConnection('game');
        Schema::swap($connection->getSchemaBuilder());

        try {
            Schema::create('event_schedule_entries', function (Blueprint $table): void {
                $table->string('event_key');
            });
            $connection->table('event_schedule_entries')->insert(['event_key' => 'monthly-final']);
            $migration = require database_path('migrations/2026_11_15_030442_add_days_of_month_to_event_schedule_entries_table.php');
            $migration->up();

            self::assertNull($connection->table('event_schedule_entries')->value('days_of_month'));
            $connection->table('event_schedule_entries')->update(['days_of_month' => '[13]']);
            self::assertSame('[13]', $connection->table('event_schedule_entries')->value('days_of_month'));

            try {
                $migration->down();
                self::fail('Monthly restrictions must not silently become daily recurrences.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Monthly event restrictions', $exception->getMessage());
                self::assertSame('[13]', $connection->table('event_schedule_entries')->value('days_of_month'));
            }

            $connection->table('event_schedule_entries')->update(['days_of_month' => '[]']);
            $migration->down();
            self::assertFalse(Schema::hasColumn('event_schedule_entries', 'days_of_month'));
            self::assertSame('monthly-final', $connection->table('event_schedule_entries')->value('event_key'));
        } finally {
            Schema::dropIfExists('event_schedule_entries');
            $database->setDefaultConnection($defaultConnection);
            Schema::swap($originalSchema);
        }
    }
}
