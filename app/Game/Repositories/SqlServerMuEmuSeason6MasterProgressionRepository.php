<?php

namespace App\Game\Repositories;

use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Data\MasterProgression;
use Illuminate\Database\DatabaseManager;

/**
 * Reads standard Master Level progression from the MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6MasterProgressionRepository implements MasterProgressionRepository
{
    /**
     * Creates the adapter with the configured game database manager.
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Returns only Master progression belonging to the supplied account.
     *
     * @return array<string, MasterProgression>
     */
    public function forAccount(string $accountId): array
    {
        $rows = $this->database
            ->connection('game')
            ->table('MasterSkillTree as master_progression')
            ->join('Character as characters', 'master_progression.Name', '=', 'characters.Name')
            ->where('characters.AccountID', $accountId)
            ->orderBy('characters.Name')
            ->get([
                'characters.Name as character_name',
                'master_progression.MasterLevel as master_level',
                'master_progression.MasterPoint as master_points',
                'master_progression.MasterExperience as master_experience',
            ]);
        $progressionByCharacter = [];

        foreach ($rows as $row) {
            $characterName = (string) $row->character_name;
            $progressionByCharacter[$characterName] = new MasterProgression(
                characterName: $characterName,
                level: max(0, (int) $row->master_level),
                availablePoints: max(0, (int) $row->master_points),
                experience: max(0, (int) $row->master_experience),
            );
        }

        return $progressionByCharacter;
    }
}
