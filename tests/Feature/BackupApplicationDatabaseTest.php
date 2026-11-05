<?php

namespace Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use Tests\TestCase;

/**
 * Verifies safe and restorable snapshots of the application-owned database.
 */
final class BackupApplicationDatabaseTest extends TestCase
{
    /**
     * Gives the SQLite-only backup command an isolated in-memory source.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'backup_testing');
        config()->set('database.connections.backup_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $this->app->make(DatabaseManager::class)->purge('backup_testing');
    }

    /**
     * Confirms the command creates a readable snapshot containing committed application data.
     */
    public function test_command_creates_consistent_private_sqlite_snapshot(): void
    {
        $backupName = 'application-backup-test.sqlite';
        $backupPath = storage_path('app/private/backups/'.$backupName);

        DB::statement('CREATE TABLE IF NOT EXISTS backup_probe (value TEXT NOT NULL)');
        DB::table('backup_probe')->delete();
        DB::table('backup_probe')->insert([
            'value' => 'verified-snapshot',
        ]);
        @unlink($backupPath);

        try {
            $this->artisan('app:backup', [
                '--name' => $backupName,
            ])
                ->expectsOutputToContain('Application database backup created')
                ->assertSuccessful();

            $this->assertFileExists($backupPath);

            $backup = new PDO('sqlite:'.$backupPath);
            $storedValue = $backup
                ->query('SELECT value FROM backup_probe LIMIT 1')
                ?->fetchColumn();

            $this->assertSame('verified-snapshot', $storedValue);
        } finally {
            DB::statement('DROP TABLE IF EXISTS backup_probe');
            @unlink($backupPath);
        }
    }

    /**
     * Confirms user input cannot move a snapshot outside the private backup directory.
     */
    public function test_command_rejects_directory_traversal_in_backup_name(): void
    {
        $unsafePath = storage_path('app/private/escaped.sqlite');
        @unlink($unsafePath);

        $this->artisan('app:backup', [
            '--name' => '../escaped.sqlite',
        ])
            ->expectsOutputToContain('without directories')
            ->assertFailed();

        $this->assertFileDoesNotExist($unsafePath);
    }

    /**
     * Confirms an existing snapshot is never silently overwritten by a repeated command.
     */
    public function test_command_preserves_existing_backup_with_same_name(): void
    {
        $backupName = 'existing-application-backup.sqlite';
        $backupPath = storage_path('app/private/backups/'.$backupName);

        if (! is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }

        file_put_contents($backupPath, 'existing-backup');

        try {
            $this->artisan('app:backup', [
                '--name' => $backupName,
            ])
                ->expectsOutputToContain('already exists')
                ->assertFailed();

            $this->assertSame('existing-backup', file_get_contents($backupPath));
        } finally {
            @unlink($backupPath);
        }
    }

    /**
     * Confirms the generated filename is deterministic, private and suitable for automation.
     */
    public function test_command_generates_timestamped_name_when_name_is_omitted(): void
    {
        $this->travelTo('2026-11-02 16:00:00');

        $backupName = 'application-20261102-160000.sqlite';
        $backupPath = storage_path('app/private/backups/'.$backupName);
        @unlink($backupPath);

        try {
            $this->artisan('app:backup')
                ->expectsOutputToContain($backupName)
                ->assertSuccessful();

            $this->assertFileExists($backupPath);
        } finally {
            @unlink($backupPath);
        }
    }

    /**
     * Confirms the command fails closed instead of pretending to back up another database driver.
     */
    public function test_command_rejects_unsupported_application_database_driver(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('getDriverName')
            ->once()
            ->andReturn('mysql');

        $database = $this->mock(DatabaseManager::class);
        $database
            ->shouldReceive('connection')
            ->once()
            ->andReturn($connection);

        $this->artisan('app:backup')
            ->expectsOutputToContain('supports only the SQLite')
            ->assertFailed();
    }
}
