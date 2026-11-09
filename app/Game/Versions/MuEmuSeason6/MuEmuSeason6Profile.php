<?php

namespace App\Game\Versions\MuEmuSeason6;

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
                'memb_guid', 'memb___id', 'memb__pwd', 'mail_addr', 'bloc_code', 'ctl1_code',
                'AccountLevel', 'AccountExpireDate', 'WarehouseCount',
            ],
            'Character' => [
                'AccountID', 'Name', 'cLevel', 'LevelUpPoint', 'Class', 'Money', 'Leadership',
                'Inventory', 'ResetCount', 'MasterResetCount',
            ],
            'Warehouse' => ['AccountID', 'Items', 'Money'],
            'ExtWarehouse' => ['AccountID', 'Items', 'Money', 'Number'],
            'MEMB_STAT' => ['memb___id', 'ConnectStat', 'ConnectTM', 'DisConnectTM', 'OnlineHours'],
            'AccountCharacter' => [
                'Id', 'GameID1', 'GameID2', 'GameID3', 'GameID4', 'GameID5', 'GameID6',
                'GameID7', 'GameID8', 'GameID9', 'GameID10', 'GameIDC', 'ExtWarehouse',
            ],
            'Guild' => ['G_Name', 'G_Mark', 'G_Master', 'G_Score'],
            'GuildMember' => ['Name', 'G_Name'],
            'GameServerInfo' => ['ItemCount'],
            'MuCastle_DATA' => [
                'MAP_SVR_GROUP', 'SIEGE_START_DATE', 'SIEGE_END_DATE', 'SIEGE_ENDED',
                'CASTLE_OCCUPY', 'OWNER_GUILD',
            ],
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
            new CharacterClassDefinition(80, 'su', 'SU', 'Summoner', '/images/characters/summoner.gif', 'summoner', 1),
            new CharacterClassDefinition(81, 'bs', 'BS', 'Bloody Summoner', '/images/characters/summoner.gif', 'summoner', 2, 80),
            new CharacterClassDefinition(82, 'di', 'DI', 'Dimension Master', '/images/characters/summoner.gif', 'summoner', 3, 81),
            new CharacterClassDefinition(96, 'rf', 'RF', 'Rage Fighter', '/images/characters/rage-fighter.gif', 'rage-fighter', 1),
            new CharacterClassDefinition(98, 'fm', 'FM', 'Fist Master', '/images/characters/rage-fighter.gif', 'rage-fighter', 3, 96),
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
     * Returns no item services until the isolated MuEMU codec is installed.
     *
     * @return array<class-string, class-string>
     */
    public function itemServiceBindings(): array
    {
        return [];
    }

    /**
     * Returns no repositories until the isolated MuEMU adapters are installed.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array
    {
        return [];
    }
}
