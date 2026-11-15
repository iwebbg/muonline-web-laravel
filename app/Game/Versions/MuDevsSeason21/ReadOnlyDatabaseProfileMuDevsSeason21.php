<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Versions\Contracts\GameDatabaseProfile;

/**
 * Limits initial S21 commissioning to reads of the inspected game schema.
 */
final class ReadOnlyDatabaseProfileMuDevsSeason21 implements GameDatabaseProfile
{
    /**
     * Returns the schema needed to inspect native account and character systems.
     *
     * @return array<string, list<string>>
     */
    public function requiredDatabaseSchema(): array
    {
        return [
            'MEMB_INFO' => ['memb___id', 'memb__pwd', 'memb_guid', 'memb_name', 'sno__numb', 'mail_addr', 'bloc_code', 'ctl1_code', 'AccountLevel', 'AccountExpireDate'],
            'MEMB_STAT' => ['memb___id', 'ConnectStat', 'ConnectTM', 'DisConnectTM', 'OnlineHours'],
            'AccountCharacter' => ['Id', 'AccountIndex', 'ExtWarehouse', 'GameID1', 'CharacterIndex_1'],
            'Character' => [
                'AccountID', 'Name', 'CharacterIndex', 'Class', 'cLevel', 'LevelUpPoint',
                'Experience', 'Strength', 'Dexterity', 'Vitality', 'Energy', 'Leadership',
                'Inventory', 'ExtInventory', 'Money', 'RuudMoney', 'ResetCount',
                'MasterResetCount', 'EnhanceResetCount', 'MapNumber', 'MapPosX', 'MapPosY',
                'GiantMountType', 'GiantMountStatus',
                'PkCount', 'PkLevel', 'PkTime', 'CtlCode', 'Quest', 'MagicList',
            ],
            'warehouse' => ['AccountID', 'AccountIndex', 'Items', 'Money'],
            'ExtWarehouse' => ['AccountID', 'AccountIndex', 'Number', 'Items', 'Money'],
            'MuCastle_DATA' => ['MAP_SVR_GROUP', 'SIEGE_START_DATE', 'SIEGE_END_DATE', 'SIEGE_ENDED', 'CASTLE_OCCUPY', 'OWNER_GUILD'],
            'Guild' => ['G_Name', 'G_Master', 'G_Mark', 'G_Score', 'G_Count', 'G_Notice'],
            'GuildMember' => ['Name', 'G_Name'],
            'MasterSkillTree' => ['CharacterIndex', 'Name', 'MasterLevel', 'MasterPoint', 'MasterExperience', 'MasterSkill'],
            'EnhanceSkillTree' => ['CharacterIndex', 'Name', 'EnhancePoint', 'EnhanceSkill', 'EnhanceSkillPassive'],
            'AbilityEnhancementCore' => ['Account', 'Name', 'CharacterIndex', 'Inventory'],
            'CashShopData' => ['AccountID', 'WCoinC', 'WCoinP', 'GoblinPoint'],
            'LuckyCoin' => ['AccountID', 'LuckyCoin'],
            'Gens_Rank' => ['Name', 'CharacterIndex', 'Family', 'Contribution'],
            'PentagramJewel' => ['Account', 'Name', 'Type', 'Index', 'ItemSection', 'ItemType'],
            'MuunInventory' => ['Name', 'CharacterIndex', 'Items'],
            'EventInventory' => ['Name', 'CharacterIndex', 'Items'],
            'GremoryCase' => ['Account', 'Name', 'AccountIndex', 'CharacterIndex', 'ItemInfo', 'ExpireDate'],
            'ArtifactSystem' => ['Name', 'CharacterIndex', 'GroupIndex', 'Slot', 'ItemInfo'],
            'WingCoreItem' => ['coreSerial', 'lastCharacterIndex', 'itemInfo'],
        ];
    }

    /**
     * Grants only reads, without item serial execution or game-data mutations.
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
