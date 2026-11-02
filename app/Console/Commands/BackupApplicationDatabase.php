<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use PDO;
use Throwable;

#[Signature('app:backup {--name= : Optional .sqlite filename stored in the private backup directory}')]
/**
 * Creates a transactionally consistent snapshot of the application-owned SQLite database.
 */
#[Description('Back up the application-owned SQLite database without touching MuOnline data.')]
final class BackupApplicationDatabase extends Command
{
    /**
     * Creates one private SQLite snapshot and refuses unsafe paths or unsupported drivers.
     */
    public function handle(
        DatabaseManager $database,
        Filesystem $files,
    ): int {
        $connection = $database->connection();

        if ($connection->getDriverName() !== 'sqlite') {
            $this->components->error('Application backup currently supports only the SQLite application database.');

            return self::FAILURE;
        }

        $requestedName = $this->option('name');
        $backupName = is_string($requestedName) && $requestedName !== ''
            ? $requestedName
            : 'application-'.now()->format('Ymd-His').'.sqlite';

        if (! $this->isSafeBackupName($backupName)) {
            $this->components->error('Backup name must be a simple .sqlite filename without directories.');

            return self::FAILURE;
        }

        $backupDirectory = storage_path('app/private/backups');
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$backupName;

        $files->ensureDirectoryExists($backupDirectory);

        if ($files->exists($backupPath)) {
            $this->components->error('A backup with this name already exists.');

            return self::FAILURE;
        }

        try {
            $pdo = $connection->getPdo();
            $quotedBackupPath = $pdo->quote($backupPath);
            $pdo->exec("VACUUM INTO {$quotedBackupPath}");

            if (! $this->backupPassesIntegrityCheck($backupPath)) {
                throw new \RuntimeException('SQLite integrity verification failed.');
            }
        } catch (Throwable) {
            $files->delete($backupPath);
            $this->components->error('Application database backup failed.');

            return self::FAILURE;
        }

        $backupSize = $files->size($backupPath);

        $this->components->info("Application database backup created: {$backupName}");
        $this->line("Bytes: {$backupSize}");

        return self::SUCCESS;
    }

    /**
     * Accepts only bounded filenames that cannot escape the private backup directory.
     */
    private function isSafeBackupName(string $backupName): bool
    {
        if (strlen($backupName) > 100) {
            return false;
        }

        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.sqlite\z/', $backupName) === 1
            && ! str_contains($backupName, '..');
    }

    /**
     * Opens the completed snapshot independently and requires SQLite's full integrity check to pass.
     */
    private function backupPassesIntegrityCheck(string $backupPath): bool
    {
        $backup = new PDO('sqlite:'.$backupPath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $integrityResult = $backup
            ->query('PRAGMA integrity_check')
            ?->fetchColumn();

        return $integrityResult === 'ok';
    }
}
