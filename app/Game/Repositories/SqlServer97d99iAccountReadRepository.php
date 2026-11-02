<?php

namespace App\Game\Repositories;

use App\Game\Contracts\AccountReadRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Versions\Version97d99i\CharacterClassMapper;
use App\Game\Versions\Version97d99i\CharacterImageMapper;
use App\Game\Versions\Version97d99i\MapNameMapper;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Reads private account characters from the MuOnline 97d+99i schema.
 */
final readonly class SqlServer97d99iAccountReadRepository implements AccountReadRepository
{
    /**
     * Creates the repository with the database manager and version-specific mappers.
     */
    public function __construct(
        private DatabaseManager $database,
        private CharacterClassMapper $classMapper,
        private CharacterImageMapper $imageMapper,
        private MapNameMapper $mapNameMapper,
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
            ->get($this->characterColumns());
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
            ->first($this->characterColumns());

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
            ->where('characters.AccountID', $accountId);
    }

    /**
     * Lists the verified 97d+99i fields exposed through the account boundary.
     *
     * @return list<string>
     */
    private function characterColumns(): array
    {
        return [
            'characters.Name as name',
            'characters.cLevel as level',
            'characters.Class as class_code',
            'characters.Money as zen',
            'characters.LevelUpPoint as level_up_points',
            'characters.Strength as strength',
            'characters.Dexterity as agility',
            'characters.Vitality as vitality',
            'characters.Energy as energy',
            'characters.MapNumber as map_number',
            'characters.Resets as resets',
            'characters.GrandResets as grand_resets',
            'account_status.ConnectStat as connect_stat',
        ];
    }

    /**
     * Normalizes one legacy database row to the version-independent character DTO.
     */
    private function mapCharacter(stdClass $row): AccountCharacter
    {
        $classCode = (int) $row->class_code;

        return new AccountCharacter(
            name: (string) $row->name,
            className: $this->classMapper->nameFor($classCode),
            classCode: $classCode,
            image: $this->imageMapper->pathFor($classCode),
            level: (int) $row->level,
            resets: (int) $row->resets,
            grandResets: (int) $row->grand_resets,
            zen: (int) $row->zen,
            points: (int) $row->level_up_points,
            strength: (int) $row->strength,
            agility: (int) $row->agility,
            vitality: (int) $row->vitality,
            energy: (int) $row->energy,
            map: $this->mapNameMapper->nameFor((int) $row->map_number),
            online: (int) ($row->connect_stat ?? 0) === 1,
        );
    }
}
