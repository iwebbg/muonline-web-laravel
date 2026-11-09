<?php

namespace App\Game\Versions\MuEmuSeason6;

use App\Game\Contracts\AccountActivationRepository;
use App\Game\Contracts\AccountCredentialRepository;
use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\AccountRegistrationRepository;
use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\CharacterMoveRepository;
use App\Game\Contracts\CharacterPkClearRepository;
use App\Game\Contracts\CharacterResetRepository;
use App\Game\Contracts\CharacterServiceRepository;
use App\Game\Contracts\CharacterStatAllocationRepository;
use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Contracts\CharacterVipRepository;
use App\Game\Contracts\GensMembershipRepository;
use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Contracts\ModerationRepository;
use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountActivationRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountCredentialRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountRegistrationRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CastleSiegeReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterGrandResetRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterInventoryEscrowRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterMoveRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterPkClearRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterResetRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterServiceRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterStatAllocationRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterStatResetRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterVipRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6GensMembershipRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ItemSerialRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ItemStorageRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6MarketItemEscrowRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6MasterProgressionRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ModerationRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6RealmReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ZenBankRepository;
use App\Game\Repositories\SqlServerOnlineAccountRepository;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\CharacterStatDefinition;
use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\GameVersionCapabilities;
use App\Game\Versions\ShopClassScopeDefinition;
use App\Stats\CharacterStats;
use InvalidArgumentException;

/**
 * Defines the MuEMU Season 6 Update 32 classes, stats and database contract.
 */
final class MuEmuSeason6Profile implements GameDatabaseProfile, GameVersionProfile
{
    /**
     * Returns the minimum MuEMU Season 6 schema consumed by its adapters.
     *
     * @return array<string, list<string>>
     */
    public function requiredDatabaseSchema(): array
    {
        return [
            'MEMB_INFO' => [
                'memb_guid', 'memb___id', 'memb__pwd', 'memb_name', 'sno__numb', 'mail_addr',
                'bloc_code', 'ctl1_code', 'AccountLevel', 'AccountExpireDate', 'WarehouseCount',
            ],
            'Character' => [
                'AccountID', 'Name', 'cLevel', 'LevelUpPoint', 'Class', 'Experience', 'Strength',
                'Dexterity', 'Vitality', 'Energy', 'Leadership', 'Inventory', 'Money', 'MapNumber',
                'MapPosX', 'MapPosY', 'PkCount', 'PkLevel', 'PkTime', 'CtlCode', 'ResetCount',
                'MasterResetCount', 'ExtInventory',
            ],
            'Warehouse' => ['AccountID', 'Items', 'Money'],
            'ExtWarehouse' => ['AccountID', 'Items', 'Money', 'Number'],
            'MEMB_STAT' => ['memb___id', 'ConnectStat', 'ConnectTM', 'DisConnectTM', 'OnlineHours'],
            'AccountCharacter' => [
                'Id', 'GameID1', 'GameID2', 'GameID3', 'GameID4', 'GameID5', 'GameID6',
                'GameID7', 'GameID8', 'GameID9', 'GameID10', 'GameIDC', 'ExtWarehouse',
            ],
            'Guild' => ['G_Name', 'G_Mark', 'G_Master', 'G_Score', 'G_Count', 'G_Notice'],
            'GuildMember' => ['Name', 'G_Name'],
            'GameServerInfo' => ['ItemCount'],
            'MuCastle_DATA' => [
                'MAP_SVR_GROUP', 'SIEGE_START_DATE', 'SIEGE_END_DATE', 'SIEGE_ENDED',
                'CASTLE_OCCUPY', 'OWNER_GUILD',
            ],
            'MasterSkillTree' => [
                'Name', 'MasterLevel', 'MasterPoint', 'MasterExperience', 'MasterSkill',
            ],
            'Gens_Rank' => ['Name', 'Family', 'Contribution'],
        ];
    }

    /**
     * Returns the least-privilege SQL access planned for the isolated adapter.
     *
     * @return array<string, list<string>>
     */
    public function databaseObjectPermissions(): array
    {
        return [
            'dbo.MEMB_INFO' => ['SELECT', 'INSERT', 'UPDATE'],
            'dbo.MEMB_STAT' => ['SELECT'],
            'dbo.AccountCharacter' => ['SELECT', 'UPDATE'],
            'dbo.Character' => ['SELECT', 'UPDATE'],
            'dbo.Warehouse' => ['SELECT', 'UPDATE'],
            'dbo.ExtWarehouse' => ['SELECT', 'UPDATE'],
            'dbo.Guild' => ['SELECT', 'UPDATE'],
            'dbo.GuildMember' => ['SELECT', 'UPDATE'],
            'dbo.GameServerInfo' => ['SELECT'],
            'dbo.MuCastle_DATA' => ['SELECT'],
            'dbo.MasterSkillTree' => ['SELECT'],
            'dbo.Gens_Rank' => ['SELECT'],
            'dbo.WZ_GetItemSerial' => ['EXECUTE', 'VIEW DEFINITION'],
        ];
    }

    /**
     * Returns the optional systems verified in the MuEMU database export.
     */
    public function capabilities(): GameVersionCapabilities
    {
        return new GameVersionCapabilities(
            grandResets: true,
            characterVip: false,
            billingRecord: false,
            itemGenerator: true,
            castleSiege: true,
            masterProgression: true,
            gens: true,
        );
    }

    /**
     * Returns the stable environment key for the MuEMU Season 6 profile.
     */
    public function key(): string
    {
        return 'muemu_season_6';
    }

    /**
     * Returns every MuEMU Season 6 class evolution supported by Update 32.
     *
     * @return list<CharacterClassDefinition>
     */
    public function classes(): array
    {
        return [
            new CharacterClassDefinition(0, 'dw', 'DW', 'Dark Wizard', '/images/characters/soul-master.jpg', 'wizard', 1),
            new CharacterClassDefinition(1, 'sm', 'SM', 'Soul Master', '/images/characters/soul-master.jpg', 'wizard', 2, 0),
            new CharacterClassDefinition(2, 'gm', 'GM', 'Grand Master', '/images/characters/soul-master.jpg', 'wizard', 3, 1),
            new CharacterClassDefinition(16, 'dk', 'DK', 'Dark Knight', '/images/characters/blade-knight.png', 'knight', 1),
            new CharacterClassDefinition(17, 'bk', 'BK', 'Blade Knight', '/images/characters/blade-knight.png', 'knight', 2, 16),
            new CharacterClassDefinition(18, 'bm', 'BM', 'Blade Master', '/images/characters/blade-knight.png', 'knight', 3, 17),
            new CharacterClassDefinition(32, 'fe', 'FE', 'Fairy Elf', '/images/characters/muse-elf.png', 'elf', 1),
            new CharacterClassDefinition(33, 'me', 'ME', 'Muse Elf', '/images/characters/muse-elf.png', 'elf', 2, 32),
            new CharacterClassDefinition(34, 'he', 'HE', 'High Elf', '/images/characters/muse-elf.png', 'elf', 3, 33),
            new CharacterClassDefinition(48, 'mg', 'MG', 'Magic Gladiator', '/images/characters/magic-gladiator.png', 'magic-gladiator', 1),
            new CharacterClassDefinition(50, 'dm', 'DM', 'Duel Master', '/images/characters/magic-gladiator.png', 'magic-gladiator', 3, 48),
            new CharacterClassDefinition(64, 'dl', 'DL', 'Dark Lord', '/images/characters/dark-lord.png', 'dark-lord', 1),
            new CharacterClassDefinition(66, 'le', 'LE', 'Lord Emperor', '/images/characters/dark-lord.png', 'dark-lord', 3, 64),
            new CharacterClassDefinition(80, 'su', 'SU', 'Summoner', '/images/characters/summoner.jpg', 'summoner', 1),
            new CharacterClassDefinition(81, 'bs', 'BS', 'Bloody Summoner', '/images/characters/summoner.jpg', 'summoner', 2, 80),
            new CharacterClassDefinition(82, 'di', 'DI', 'Dimension Master', '/images/characters/summoner.jpg', 'summoner', 3, 81),
            new CharacterClassDefinition(96, 'rf', 'RF', 'Rage Fighter', '/images/characters/rage-fighter.jpg', 'rage-fighter', 1),
            new CharacterClassDefinition(98, 'fm', 'FM', 'Fist Master', '/images/characters/rage-fighter.jpg', 'rage-fighter', 3, 96),
        ];
    }

    /**
     * Resolves one exact MuEMU Character.Class value.
     */
    public function classForCode(int $classCode): ?CharacterClassDefinition
    {
        foreach ($this->classes() as $classDefinition) {
            if ($classDefinition->code === $classCode) {
                return $classDefinition;
            }
        }

        return null;
    }

    /**
     * Resolves one public class-filter key.
     */
    public function classForKey(string $classKey): ?CharacterClassDefinition
    {
        foreach ($this->classes() as $classDefinition) {
            if ($classDefinition->key === $classKey) {
                return $classDefinition;
            }
        }

        return null;
    }

    /**
     * Returns the seven MuEMU Season 6 class families used by shop filters.
     *
     * @return list<ShopClassScopeDefinition>
     */
    public function shopClassScopes(): array
    {
        return [
            new ShopClassScopeDefinition('all', 'All'),
            new ShopClassScopeDefinition('dw_sm', 'DW / SM / GM'),
            new ShopClassScopeDefinition('dk_bk', 'DK / BK / BM'),
            new ShopClassScopeDefinition('elf_me', 'Elf / ME / HE'),
            new ShopClassScopeDefinition('mg', 'Magic Gladiator / Duel Master'),
            new ShopClassScopeDefinition('dl_le', 'Dark Lord / Lord Emperor'),
            new ShopClassScopeDefinition('su_bs', 'Summoner / Bloody Summoner / Dimension Master'),
            new ShopClassScopeDefinition('rf_fm', 'Rage Fighter / Fist Master'),
        ];
    }

    /**
     * Returns the five attributes stored by the MuEMU Character table.
     *
     * @return list<CharacterStatDefinition>
     */
    public function stats(): array
    {
        return [
            new CharacterStatDefinition('strength', 'Strength', 'Сила', 'Strength', 0, 65_000),
            new CharacterStatDefinition('agility', 'Agility', 'Ловкост', 'Dexterity', 0, 65_000),
            new CharacterStatDefinition('vitality', 'Vitality', 'Жизненост', 'Vitality', 0, 65_000),
            new CharacterStatDefinition('energy', 'Energy', 'Енергия', 'Energy', 0, 65_000),
            new CharacterStatDefinition('command', 'Command', 'Команда', 'Leadership', 0, 65_000),
        ];
    }

    /**
     * Returns the attributes available to one supported class evolution.
     *
     * @return list<CharacterStatDefinition>
     */
    public function statsForClass(int $classCode): array
    {
        $classDefinition = $this->classForCode($classCode);

        if ($classDefinition === null) {
            throw new InvalidArgumentException(
                sprintf('Unsupported MuEMU Season 6 character class code: %d.', $classCode),
            );
        }

        if ($classDefinition->family === 'dark-lord') {
            return $this->stats();
        }

        return array_values(array_filter(
            $this->stats(),
            static fn (CharacterStatDefinition $statDefinition): bool => $statDefinition->key !== 'command',
        ));
    }

    /**
     * Returns the level-one stats from MuEMU DefaultClassInfo.txt.
     */
    public function baseStatsForClass(int $classCode): CharacterStats
    {
        return match ($classCode) {
            0, 1, 2 => CharacterStats::fromArray([
                'strength' => 18, 'agility' => 18, 'vitality' => 15, 'energy' => 30,
            ]),
            16, 17, 18 => CharacterStats::fromArray([
                'strength' => 28, 'agility' => 20, 'vitality' => 25, 'energy' => 10,
            ]),
            32, 33, 34 => CharacterStats::fromArray([
                'strength' => 22, 'agility' => 25, 'vitality' => 20, 'energy' => 15,
            ]),
            48, 50 => CharacterStats::fromArray([
                'strength' => 26, 'agility' => 26, 'vitality' => 26, 'energy' => 26,
            ]),
            64, 66 => CharacterStats::fromArray([
                'strength' => 26, 'agility' => 20, 'vitality' => 20, 'energy' => 15, 'command' => 25,
            ]),
            80, 81, 82 => CharacterStats::fromArray([
                'strength' => 21, 'agility' => 21, 'vitality' => 18, 'energy' => 23,
            ]),
            96, 98 => CharacterStats::fromArray([
                'strength' => 32, 'agility' => 27, 'vitality' => 25, 'energy' => 20,
            ]),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported MuEMU Season 6 character class code: %d.', $classCode),
            ),
        };
    }

    /**
     * Returns the isolated MuEMU item services as one atomic bundle.
     *
     * @return array<class-string, class-string>
     */
    public function itemServiceBindings(): array
    {
        return [
            ItemCatalog::class => ItemCatalogMuEmuSeason6::class,
            ItemCodec::class => ItemCodecMuEmuSeason6::class,
            ItemGrantRules::class => ItemGrantRulesMuEmuSeason6::class,
            ItemGeneratorCatalog::class => ItemGeneratorCatalogMuEmuSeason6::class,
        ];
    }

    /**
     * Returns every SQL adapter selected as one atomic MuEMU bundle.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array
    {
        return [
            RealmReadRepository::class => SqlServerMuEmuSeason6RealmReadRepository::class,
            CastleSiegeReadRepository::class => SqlServerMuEmuSeason6CastleSiegeReadRepository::class,
            GensMembershipRepository::class => SqlServerMuEmuSeason6GensMembershipRepository::class,
            AccountActivationRepository::class => SqlServerMuEmuSeason6AccountActivationRepository::class,
            AccountCredentialRepository::class => SqlServerMuEmuSeason6AccountCredentialRepository::class,
            AccountPasswordRecoveryRepository::class => SqlServerMuEmuSeason6AccountPasswordRecoveryRepository::class,
            AccountRegistrationRepository::class => SqlServerMuEmuSeason6AccountRegistrationRepository::class,
            AccountReadRepository::class => SqlServerMuEmuSeason6AccountReadRepository::class,
            CharacterResetRepository::class => SqlServerMuEmuSeason6CharacterResetRepository::class,
            CharacterGrandResetRepository::class => SqlServerMuEmuSeason6CharacterGrandResetRepository::class,
            CharacterMoveRepository::class => SqlServerMuEmuSeason6CharacterMoveRepository::class,
            CharacterPkClearRepository::class => SqlServerMuEmuSeason6CharacterPkClearRepository::class,
            CharacterStatAllocationRepository::class => SqlServerMuEmuSeason6CharacterStatAllocationRepository::class,
            CharacterStatResetRepository::class => SqlServerMuEmuSeason6CharacterStatResetRepository::class,
            CharacterServiceRepository::class => SqlServerMuEmuSeason6CharacterServiceRepository::class,
            CharacterVipRepository::class => SqlServerMuEmuSeason6CharacterVipRepository::class,
            CharacterInventoryEscrowRepository::class => SqlServerMuEmuSeason6CharacterInventoryEscrowRepository::class,
            ItemStorageRepository::class => SqlServerMuEmuSeason6ItemStorageRepository::class,
            ItemEscrowRepository::class => SqlServerMuEmuSeason6MarketItemEscrowRepository::class,
            ItemSerialRepository::class => SqlServerMuEmuSeason6ItemSerialRepository::class,
            MarketItemEscrowRepository::class => SqlServerMuEmuSeason6MarketItemEscrowRepository::class,
            ModerationRepository::class => SqlServerMuEmuSeason6ModerationRepository::class,
            MasterProgressionRepository::class => SqlServerMuEmuSeason6MasterProgressionRepository::class,
            OnlineAccountRepository::class => SqlServerOnlineAccountRepository::class,
            ZenBankRepository::class => SqlServerMuEmuSeason6ZenBankRepository::class,
        ];
    }
}
