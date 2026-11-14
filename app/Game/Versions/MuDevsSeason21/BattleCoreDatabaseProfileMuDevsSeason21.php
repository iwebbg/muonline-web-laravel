<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Versions\Contracts\GameDatabaseProfile;

/**
 * Limits BattleCore commissioning to event participant, reward and ranking reads.
 */
final class BattleCoreDatabaseProfileMuDevsSeason21 implements GameDatabaseProfile
{
    /**
     * Returns the event tables present in the supplied separate BattleCore schema.
     *
     * @return array<string, list<string>>
     */
    public function requiredDatabaseSchema(): array
    {
        return [
            'BattleCoreInfo' => ['AccountID', 'Name', 'ServerCode', 'RegisterState'],
            'BattleCoreUser' => ['AccountID', 'Name', 'ServerCode', 'RegisterState'],
            'BattleCoreReward' => ['AccountID', 'RealName', 'ServerCode', 'TookState'],
            'DevilSquareFinal_Ranking' => ['mRank', 'mAccountID1', 'mAccountID2', 'mUserName1', 'mUserName2', 'mPoint'],
            'DevilSquareFinal_Scores' => ['mAccountID1', 'mAccountID2', 'mName1', 'mName2', 'mPoint'],
        ];
    }

    /**
     * Grants no ability to register players, claim rewards or copy characters.
     *
     * @return array<string, list<string>>
     */
    public function databaseObjectPermissions(): array
    {
        $permissions = [];

        foreach (array_keys($this->requiredDatabaseSchema()) as $table) {
            $permissions['dbo.'.$table] = ['SELECT'];
        }

        return $permissions;
    }
}
