<?php

namespace App\Game\Repositories;

use App\Game\Contracts\GensMembershipRepository;
use App\Game\Data\GensFamily;
use App\Game\Data\GensMembership;
use App\Game\Data\GensRankingEntry;
use App\Game\Data\GensRankingPage;
use App\Game\Data\GensRankingQuery;
use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

/**
 * Reads standard Gens membership and contribution from the MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6GensMembershipRepository implements GensMembershipRepository
{
    /**
     * Creates the adapter with the configured game database manager.
     */
    public function __construct(
        private DatabaseManager $database,
        private GameVersionProfile $versionProfile,
    ) {}

    /**
     * Returns only Gens memberships belonging to the supplied account.
     *
     * Rank follows the server function: contribution below 10,000 remains
     * unranked, while higher contributors receive a dense family rank.
     *
     * @return array<string, GensMembership>
     */
    public function forAccount(string $accountId): array
    {
        $rows = $this->database
            ->connection('game')
            ->table('Character as characters')
            ->joinSub($this->rankedMembershipQuery('game'), 'gens', function ($join): void {
                $join->on('gens.character_name', '=', 'characters.Name');
            })
            ->where('characters.AccountID', $accountId)
            ->orderBy('characters.Name')
            ->get([
                'characters.Name as character_name',
                'gens.family',
                'gens.contribution',
                'gens.gens_rank',
            ]);
        $membershipsByCharacter = [];

        foreach ($rows as $row) {
            $family = GensFamily::fromDatabaseValue((int) $row->family);

            if ($family === null) {
                continue;
            }

            $characterName = (string) $row->character_name;
            $membershipsByCharacter[$characterName] = new GensMembership(
                characterName: $characterName,
                family: $family,
                contribution: max(0, (int) $row->contribution),
                rank: max(0, (int) $row->gens_rank),
            );
        }

        return $membershipsByCharacter;
    }

    /**
     * Returns public Gens standings ordered by contribution across both families.
     */
    public function rankings(GensRankingQuery $query): GensRankingPage
    {
        $connectionName = (string) config('game.database_connections.rankings', 'game');
        $builder = $this->database
            ->connection($connectionName)
            ->table('Character as characters')
            ->joinSub($this->rankedMembershipQuery($connectionName), 'gens', function ($join): void {
                $join->on('gens.character_name', '=', 'characters.Name');
            })
            ->leftJoin('MEMB_STAT as account_status', 'characters.AccountID', '=', 'account_status.memb___id')
            ->whereIn('gens.family', [1, 2]);

        if ($query->search !== '') {
            $builder->where('characters.Name', 'like', '%'.$query->search.'%');
        }

        $total = (int) (clone $builder)->count('characters.Name');
        $rows = $builder
            ->orderByDesc('gens.contribution')
            ->orderBy('characters.Name')
            ->offset(($query->page - 1) * $query->perPage)
            ->limit($query->perPage)
            ->get([
                'characters.Name as character_name',
                'characters.Class as class_code',
                'gens.family',
                'gens.contribution',
                'gens.gens_rank',
                'account_status.ConnectStat as connect_stat',
            ]);
        $entries = [];

        foreach ($rows as $row) {
            $family = GensFamily::fromDatabaseValue((int) $row->family);

            if ($family === null) {
                continue;
            }

            $classCode = (int) $row->class_code;
            $classDefinition = $this->versionProfile->classForCode($classCode);
            $entries[] = new GensRankingEntry(
                name: (string) $row->character_name,
                classCode: $classCode,
                className: $classDefinition?->name ?? sprintf('Unknown class (%d)', $classCode),
                classAbbreviation: $classDefinition?->abbreviation ?? '??',
                image: $classDefinition?->image ?? '/images/characters/blade-knight.png',
                family: $family,
                contribution: max(0, (int) $row->contribution),
                familyRank: max(0, (int) $row->gens_rank),
                online: (int) ($row->connect_stat ?? 0) === 1,
            );
        }

        return new GensRankingPage(
            entries: $entries,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: max(1, (int) ceil($total / $query->perPage)),
        );
    }

    /**
     * Builds the family-partitioned rank source before account ownership filtering.
     */
    private function rankedMembershipQuery(string $connectionName): Builder
    {
        return $this->database
            ->connection($connectionName)
            ->table('Gens_Rank')
            ->select([
                'Name as character_name',
                'Family as family',
                'Contribution as contribution',
            ])
            ->selectRaw(
                'CASE WHEN Contribution > 9999 '
                .'THEN DENSE_RANK() OVER (PARTITION BY Family ORDER BY Contribution DESC) '
                .'ELSE 0 END as gens_rank',
            );
    }
}
