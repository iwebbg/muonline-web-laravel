<?php

namespace App\Game\Repositories;

use App\Game\Contracts\AccountReadRepository;
use App\Game\Data\AccountCharacter;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuEmuSeason6\MapNameMapperMuEmuSeason6;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Reads private account characters from the MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6AccountReadRepository implements AccountReadRepository
{
    /**
     * Creates the repository with the database manager and version-specific mappers.
     */
    public function __construct(
        private DatabaseManager $database,
        private GameVersionProfile $versionProfile,
        private MapNameMapperMuEmuSeason6 $mapNameMapper,
    ) {}

    /**
     * Returns only characters whose AccountID matches the authenticated account.
     *
     * @return list<AccountCharacter>
     */
    public function charactersFor(string $accountId): array
    {
        $rows = $this->characterQuery($accountId)
            ->orderBy('characters.Name')
            ->get();
        $characters = [];

        foreach ($rows as $row) {
            $characters[] = $this->mapCharacter($row);
        }

        return $characters;
    }

    /**
     * Returns an ownership-scoped character for reset and account operations.
     */
    public function characterFor(string $accountId, string $characterName): ?AccountCharacter
    {
        $row = $this->characterQuery($accountId)
            ->where('characters.Name', $characterName)
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->mapCharacter($row);
    }

    /**
     * Builds the ownership-scoped base query shared by private character reads.
     */
    private function characterQuery(string $accountId): Builder
    {
        return $this->database
            ->connection('game')
            ->table('Character as characters')
            ->leftJoin('MEMB_STAT as account_status', 'characters.AccountID', '=', 'account_status.memb___id')
            ->where('characters.AccountID', $accountId)
            ->select($this->characterColumns());
    }

    /**
     * Lists the verified MuEMU Season 6 fields exposed through the account boundary.
     *
     * @return list<string>
     */
    private function characterColumns(): array
    {
        $columns = [
            'characters.Name as name',
            'characters.cLevel as level',
            'characters.Class as class_code',
            'characters.Money as zen',
            'characters.LevelUpPoint as level_up_points',
            'characters.MapNumber as map_number',
            'characters.ResetCount as resets',
            'characters.MasterResetCount as grand_resets',
            'account_status.ConnectStat as connect_stat',
        ];

        foreach ($this->versionProfile->stats() as $statDefinition) {
            $columns[] = sprintf(
                'characters.%s as stat_%s',
                $statDefinition->databaseColumn,
                $statDefinition->key,
            );
        }

        return $columns;
    }

    /**
     * Normalizes one legacy database row to the version-independent character DTO.
     */
    private function mapCharacter(stdClass $row): AccountCharacter
    {
        $classCode = (int) $row->class_code;
        $classDefinition = $this->versionProfile->classForCode($classCode);
        $stats = [];

        foreach ($this->versionProfile->statsForClass($classCode) as $statDefinition) {
            $columnAlias = 'stat_'.$statDefinition->key;
            $stats[$statDefinition->key] = (int) $row->{$columnAlias};
        }

        $additionalStats = array_diff_key(
            $stats,
            array_flip(['strength', 'agility', 'vitality', 'energy']),
        );

        return new AccountCharacter(
            name: (string) $row->name,
            className: $classDefinition?->name ?? sprintf('Unknown class (%d)', $classCode),
            classCode: $classCode,
            image: $classDefinition?->image ?? '/images/characters/blade-knight.png',
            level: (int) $row->level,
            resets: (int) $row->resets,
            grandResets: (int) $row->grand_resets,
            zen: (int) $row->zen,
            points: (int) $row->level_up_points,
            strength: $stats['strength'] ?? 0,
            agility: $stats['agility'] ?? 0,
            vitality: $stats['vitality'] ?? 0,
            energy: $stats['energy'] ?? 0,
            map: $this->mapNameMapper->nameFor((int) $row->map_number),
            online: GameAccountWriteStatus::blocksWrites($row->connect_stat ?? null),
            additionalStats: $additionalStats,
        );
    }
}
