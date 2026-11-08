<?php

namespace App\Game\Versions\Version1_0M;

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
use App\Game\Contracts\CharacterStatAllocationRepository;
use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Contracts\CharacterVipRepository;
use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Contracts\ModerationRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Game\Repositories\SqlServer1_0MAccountActivationRepository;
use App\Game\Repositories\SqlServer1_0MAccountCredentialRepository;
use App\Game\Repositories\SqlServer1_0MAccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServer1_0MAccountReadRepository;
use App\Game\Repositories\SqlServer1_0MAccountRegistrationRepository;
use App\Game\Repositories\SqlServer1_0MCastleSiegeReadRepository;
use App\Game\Repositories\SqlServer1_0MCharacterGrandResetRepository;
use App\Game\Repositories\SqlServer1_0MCharacterInventoryEscrowRepository;
use App\Game\Repositories\SqlServer1_0MCharacterMoveRepository;
use App\Game\Repositories\SqlServer1_0MCharacterPkClearRepository;
use App\Game\Repositories\SqlServer1_0MCharacterResetRepository;
use App\Game\Repositories\SqlServer1_0MCharacterStatAllocationRepository;
use App\Game\Repositories\SqlServer1_0MCharacterStatResetRepository;
use App\Game\Repositories\SqlServer1_0MCharacterVipRepository;
use App\Game\Repositories\SqlServer1_0MItemSerialRepository;
use App\Game\Repositories\SqlServer1_0MItemStorageRepository;
use App\Game\Repositories\SqlServer1_0MMarketItemEscrowRepository;
use App\Game\Repositories\SqlServer1_0MModerationRepository;
use App\Game\Repositories\SqlServer1_0MRealmReadRepository;
use App\Game\Repositories\SqlServer1_0MZenBankRepository;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\CharacterStatDefinition;
use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\GameVersionCapabilities;
use App\Game\Versions\ShopClassScopeDefinition;
use App\Stats\CharacterStats;
use InvalidArgumentException;

/**
 * Defines the DarksTeam 1.0M classes, attributes and persistence adapters.
 */
final class Version1_0MProfile implements GameDatabaseProfile, GameVersionProfile
{
    /**
     * Returns the minimum DarksTeam 1.0M schema consumed by its adapters.
     *
     * @return array<string, list<string>>
     */
    public function requiredDatabaseSchema(): array
    {
        return [
            'MEMB_INFO' => ['memb_guid', 'memb___id', 'memb__pwd', 'mail_addr', 'bloc_code', 'ctl1_code'],
            'Character' => ['AccountID', 'Name', 'cLevel', 'Class', 'Money', 'Resets', 'GrandResets', 'Leadership'],
            'warehouse' => ['AccountID', 'Items', 'Money'],
            'MEMB_STAT' => ['memb___id', 'ConnectStat'],
            'Guild' => ['G_Name', 'G_Master', 'G_Score'],
            'GuildMember' => ['Name', 'G_Name'],
            'GameServerInfo' => ['ItemCount'],
            'MuCastle_DATA' => [
                'MAP_SVR_GROUP',
                'SIEGE_START_DATE',
                'SIEGE_END_DATE',
                'SIEGE_ENDED',
                'CASTLE_OCCUPY',
                'OWNER_GUILD',
            ],
        ];
    }

    /**
     * Returns only the SQL permissions used by DarksTeam 1.0M adapters.
     *
     * @return array<string, list<string>>
     */
    public function databaseObjectPermissions(): array
    {
        return [
            'dbo.MEMB_INFO' => ['SELECT', 'INSERT', 'UPDATE'],
            'dbo.MEMB_STAT' => ['SELECT'],
            'dbo.Character' => ['SELECT', 'UPDATE'],
            'dbo.warehouse' => ['SELECT', 'UPDATE'],
            'dbo.Guild' => ['SELECT'],
            'dbo.GuildMember' => ['SELECT'],
            'dbo.GameServerInfo' => ['SELECT'],
            'dbo.MuCastle_DATA' => ['SELECT'],
            'dbo.WZ_GetItemSerial' => ['EXECUTE', 'VIEW DEFINITION'],
        ];
    }

    /**
     * Returns the optional schema features implemented by DarksTeam 1.0M.
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
     * Returns the stable profile key used by environment configuration.
     */
    public function key(): string
    {
        return 'darksteam_1_0m';
    }

    /**
     * Returns every class evolution supported by DarksTeam 1.0M.
     *
     * @return list<CharacterClassDefinition>
     */
    public function classes(): array
    {
        return [
            new CharacterClassDefinition(0, 'dw', 'DW', 'Dark Wizard', '/images/characters/soul-master.jpg', 'wizard', 1),
            new CharacterClassDefinition(1, 'sm', 'SM', 'Soul Master', '/images/characters/soul-master.jpg', 'wizard', 2, 0),
            new CharacterClassDefinition(16, 'dk', 'DK', 'Dark Knight', '/images/characters/blade-knight.png', 'knight', 1),
            new CharacterClassDefinition(17, 'bk', 'BK', 'Blade Knight', '/images/characters/blade-knight.png', 'knight', 2, 16),
            new CharacterClassDefinition(32, 'fe', 'FE', 'Fairy Elf', '/images/characters/muse-elf.png', 'elf', 1),
            new CharacterClassDefinition(33, 'me', 'ME', 'Muse Elf', '/images/characters/muse-elf.png', 'elf', 2, 32),
            new CharacterClassDefinition(48, 'mg', 'MG', 'Magic Gladiator', '/images/characters/magic-gladiator.png', 'magic-gladiator', 1),
            new CharacterClassDefinition(64, 'dl', 'DL', 'Dark Lord', '/images/characters/dark-lord.png', 'dark-lord', 1),
        ];
    }

    /**
     * Resolves one exact Character.Class code.
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
     * Resolves one public ranking-filter key.
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
     * Returns all five DarksTeam 1.0M class families used by shop filters.
     *
     * @return list<ShopClassScopeDefinition>
     */
    public function shopClassScopes(): array
    {
        return [
            new ShopClassScopeDefinition('all', 'All'),
            new ShopClassScopeDefinition('dw_sm', 'DW / SM'),
            new ShopClassScopeDefinition('dk_bk', 'DK / BK'),
            new ShopClassScopeDefinition('elf_me', 'Elf / ME'),
            new ShopClassScopeDefinition('mg', 'Magic Gladiator (MG)'),
            new ShopClassScopeDefinition('dl_le', 'Dark Lord (DL)'),
        ];
    }

    /**
     * Returns every attribute stored by the DarksTeam 1.0M Character table.
     *
     * @return list<CharacterStatDefinition>
     */
    public function stats(): array
    {
        return [
            new CharacterStatDefinition('strength', 'Strength', 'Сила', 'Strength', 0, 32_767),
            new CharacterStatDefinition('agility', 'Agility', 'Ловкост', 'Dexterity', 0, 32_767),
            new CharacterStatDefinition('vitality', 'Vitality', 'Жизненост', 'Vitality', 0, 32_767),
            new CharacterStatDefinition('energy', 'Energy', 'Енергия', 'Energy', 0, 32_767),
            new CharacterStatDefinition('command', 'Command', 'Команда', 'Leadership', 0, 32_767),
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
                sprintf('Unsupported 1.0M character class code: %d.', $classCode),
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
     * Returns verified level-one attributes shared by each evolution family.
     */
    public function baseStatsForClass(int $classCode): CharacterStats
    {
        return match ($classCode) {
            0, 1 => CharacterStats::fromArray([
                'strength' => 18,
                'agility' => 18,
                'vitality' => 15,
                'energy' => 30,
            ]),
            16, 17 => CharacterStats::fromArray([
                'strength' => 28,
                'agility' => 20,
                'vitality' => 25,
                'energy' => 10,
            ]),
            32, 33 => CharacterStats::fromArray([
                'strength' => 22,
                'agility' => 25,
                'vitality' => 20,
                'energy' => 15,
            ]),
            48 => CharacterStats::fromArray([
                'strength' => 26,
                'agility' => 26,
                'vitality' => 26,
                'energy' => 26,
            ]),
            64 => CharacterStats::fromArray([
                'strength' => 26,
                'agility' => 20,
                'vitality' => 20,
                'energy' => 15,
                'command' => 25,
            ]),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported 1.0M character class code: %d.', $classCode),
            ),
        };
    }

    /**
     * Returns item services owned exclusively by the DarksTeam 1.0M adapter.
     *
     * @return array<class-string, class-string>
     */
    public function itemServiceBindings(): array
    {
        return [
            ItemCatalog::class => ItemCatalog1_0M::class,
            ItemCodec::class => ItemCodec1_0M::class,
            ItemGrantRules::class => ItemGrantRules1_0M::class,
            ItemGeneratorCatalog::class => ItemGeneratorCatalog1_0M::class,
        ];
    }

    /**
     * Returns every SQL adapter selected as one atomic 1.0M version bundle.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array
    {
        return [
            RealmReadRepository::class => SqlServer1_0MRealmReadRepository::class,
            CastleSiegeReadRepository::class => SqlServer1_0MCastleSiegeReadRepository::class,
            AccountActivationRepository::class => SqlServer1_0MAccountActivationRepository::class,
            AccountCredentialRepository::class => SqlServer1_0MAccountCredentialRepository::class,
            AccountPasswordRecoveryRepository::class => SqlServer1_0MAccountPasswordRecoveryRepository::class,
            AccountRegistrationRepository::class => SqlServer1_0MAccountRegistrationRepository::class,
            AccountReadRepository::class => SqlServer1_0MAccountReadRepository::class,
            CharacterResetRepository::class => SqlServer1_0MCharacterResetRepository::class,
            CharacterGrandResetRepository::class => SqlServer1_0MCharacterGrandResetRepository::class,
            CharacterMoveRepository::class => SqlServer1_0MCharacterMoveRepository::class,
            CharacterPkClearRepository::class => SqlServer1_0MCharacterPkClearRepository::class,
            CharacterStatAllocationRepository::class => SqlServer1_0MCharacterStatAllocationRepository::class,
            CharacterStatResetRepository::class => SqlServer1_0MCharacterStatResetRepository::class,
            CharacterVipRepository::class => SqlServer1_0MCharacterVipRepository::class,
            CharacterInventoryEscrowRepository::class => SqlServer1_0MCharacterInventoryEscrowRepository::class,
            ItemStorageRepository::class => SqlServer1_0MItemStorageRepository::class,
            ItemEscrowRepository::class => SqlServer1_0MMarketItemEscrowRepository::class,
            ItemSerialRepository::class => SqlServer1_0MItemSerialRepository::class,
            MarketItemEscrowRepository::class => SqlServer1_0MMarketItemEscrowRepository::class,
            ModerationRepository::class => SqlServer1_0MModerationRepository::class,
            ZenBankRepository::class => SqlServer1_0MZenBankRepository::class,
        ];
    }
}
