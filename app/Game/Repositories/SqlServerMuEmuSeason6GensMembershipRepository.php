<?php

namespace App\Game\Repositories;

use App\Game\Contracts\GensMembershipRepository;
use App\Game\Data\GensFamily;
use App\Game\Data\GensMembership;
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
    public function __construct(private DatabaseManager $database) {}

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
            ->joinSub($this->rankedMembershipQuery(), 'gens', function ($join): void {
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
     * Builds the family-partitioned rank source before account ownership filtering.
     */
    private function rankedMembershipQuery(): Builder
    {
        return $this->database
            ->connection('game')
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
