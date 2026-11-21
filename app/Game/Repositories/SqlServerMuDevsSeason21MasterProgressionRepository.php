<?php

namespace App\Game\Repositories;

use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Data\MasterProgression;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Reads S21 Master counters and enhancement points by stable character identity.
 */
final readonly class SqlServerMuDevsSeason21MasterProgressionRepository implements MasterProgressionRepository
{
    /**
     * Creates the adapter with the game's configured database connections.
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Returns only progression linked to the account's actual CharacterIndex values.
     *
     * @return array<string, MasterProgression>
     */
    public function forAccount(string $accountId): array
    {
        $rows = $this->progressionQuery('game')
            ->where('characters.AccountID', $accountId)
            ->orderBy('characters.Name')
            ->orderBy('characters.CharacterIndex')
            ->get();
        $progression = [];

        foreach ($rows as $row) {
            $snapshot = $this->snapshot($row);
            $progression[$snapshot->characterName] = $snapshot;
        }

        return $progression;
    }

    /**
     * Returns public progression through the ranking connection without account data.
     */
    public function forCharacter(string $characterName): ?MasterProgression
    {
        $configuredConnection = (string) config('game.database_connections.rankings', 'game');
        $connectionName = $configuredConnection === '' ? 'game' : $configuredConnection;
        $row = $this->progressionQuery($connectionName)
            ->where('characters.Name', $characterName)
            ->first();

        return $row instanceof stdClass ? $this->snapshot($row) : null;
    }

    /**
     * Joins native progression by index rather than denormalized cached names.
     */
    private function progressionQuery(string $connectionName): Builder
    {
        return $this->database
            ->connection($connectionName)
            ->table('Character as characters')
            ->join('MasterSkillTree as master', 'characters.CharacterIndex', '=', 'master.CharacterIndex')
            ->leftJoin('EnhanceSkillTree as enhancement', 'characters.CharacterIndex', '=', 'enhancement.CharacterIndex')
            ->select([
                'characters.Name as character_name',
                'master.MasterLevel as master_level',
                'master.MasterPoint as master_points',
                'master.MasterExperience as master_experience',
                'enhancement.EnhancePoint as enhancement_points',
            ]);
    }

    /**
     * Preserves native level meaning while normalizing unusable legacy counters.
     */
    private function snapshot(stdClass $row): MasterProgression
    {
        $enhancementPoints = $row->enhancement_points;

        return new MasterProgression(
            characterName: (string) $row->character_name,
            level: max(0, (int) $row->master_level),
            availablePoints: max(0, (int) $row->master_points),
            experience: max(0, (int) $row->master_experience),
            enhancementPoints: $enhancementPoints === null ? null : max(0, (int) $enhancementPoints),
        );
    }
}
