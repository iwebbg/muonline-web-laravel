<?php

namespace App\Game\Repositories;

/**
 * Applies paid identity and progression services to the MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6CharacterServiceRepository extends SqlServerCharacterServiceRepository
{
    /**
     * Returns all ten native MuEMU character slots and the active character slot.
     *
     * @return list<string>
     */
    protected function accountCharacterSlots(): array
    {
        return [
            'GameID1',
            'GameID2',
            'GameID3',
            'GameID4',
            'GameID5',
            'GameID6',
            'GameID7',
            'GameID8',
            'GameID9',
            'GameID10',
            'GameIDC',
        ];
    }

    /**
     * Returns MuEMU's native character reset counter.
     */
    protected function resetColumn(): string
    {
        return 'ResetCount';
    }
}
