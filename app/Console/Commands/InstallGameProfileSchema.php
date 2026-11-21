<?php

namespace App\Console\Commands;

use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Throwable;

#[Signature('game:install-profile-schema')]
/**
 * Installs idempotent web extensions required by the selected game profile.
 */
#[Description('Install the active game profile schema extensions with temporary administrator credentials.')]
final class InstallGameProfileSchema extends Command
{
    /**
     * Applies only the schema extension explicitly declared by the active version bundle.
     */
    public function handle(DatabaseManager $database, GameVersionProfile $versionProfile): int
    {
        if (! (bool) config('application_database.bootstrap.enabled')) {
            $this->components->error('Set APP_DB_BOOTSTRAP=true only while installing profile schema extensions.');

            return self::FAILURE;
        }

        $schemaExtensionResource = $versionProfile->capabilities()->schemaExtensionResource;

        if ($schemaExtensionResource === null) {
            $this->components->error('The active game profile does not declare schema extensions.');

            return self::FAILURE;
        }

        $databaseName = (string) config('database.connections.game.database');

        try {
            $quotedDatabase = $this->quoteIdentifier($databaseName);
            $sql = file_get_contents(resource_path($schemaExtensionResource));

            if (! is_string($sql) || trim($sql) === '') {
                throw new InvalidArgumentException('The selected game schema extension is unavailable.');
            }

            $connection = $database->connection('game_admin');
            $pdo = $connection->getPdo();
            $pdo->exec("USE {$quotedDatabase}");
            $pdo->exec($sql);
        } catch (Throwable) {
            $this->components->error('Game profile schema installation failed. Check the database and temporary administrator credentials.');

            return self::FAILURE;
        } finally {
            $database->purge('game_admin');
        }

        $this->components->info("Game profile schema extensions are ready in [{$databaseName}].");
        $this->line('Remove GAME_DB_ADMIN_PASSWORD and APP_DB_ADMIN_PASSWORD, then set APP_DB_BOOTSTRAP=false.');

        return self::SUCCESS;
    }

    /**
     * Quotes one allow-listed SQL Server database identifier.
     */
    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,127}\z/', $identifier) !== 1) {
            throw new InvalidArgumentException('Unsafe SQL Server database identifier.');
        }

        return '['.$identifier.']';
    }
}
