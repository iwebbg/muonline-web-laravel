<?php

namespace App\Game\Repositories;

use Illuminate\Database\ConnectionInterface;

/**
 * Applies paid identity services to the verified DarksTeam 97d+99i schema.
 */
final readonly class SqlServer97d99iCharacterServiceRepository extends SqlServerCharacterServiceRepository
{
    /**
     * Keeps the 97d+99i character option row attached to the renamed character.
     */
    protected function updateProfileNameReferences(
        ConnectionInterface $connection,
        string $currentName,
        string $newName,
    ): void {
        $connection->table('OptionData')
            ->where('Name', $currentName)
            ->update(['Name' => $newName]);
    }
}
