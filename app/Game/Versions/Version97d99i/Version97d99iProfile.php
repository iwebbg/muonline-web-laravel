<?php

namespace App\Game\Versions\Version97d99i;

use App\Game\Contracts\AccountActivationRepository;
use App\Game\Contracts\AccountCredentialRepository;
use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\AccountRegistrationRepository;
use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\CharacterMoveRepository;
use App\Game\Contracts\CharacterPkClearRepository;
use App\Game\Contracts\CharacterResetRepository;
use App\Game\Contracts\CharacterServiceRepository;
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
use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Game\Repositories\SqlServer97d99iAccountActivationRepository;
use App\Game\Repositories\SqlServer97d99iAccountCredentialRepository;
use App\Game\Repositories\SqlServer97d99iAccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServer97d99iAccountReadRepository;
use App\Game\Repositories\SqlServer97d99iAccountRegistrationRepository;
use App\Game\Repositories\SqlServer97d99iCharacterGrandResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterInventoryEscrowRepository;
use App\Game\Repositories\SqlServer97d99iCharacterMoveRepository;
use App\Game\Repositories\SqlServer97d99iCharacterPkClearRepository;
use App\Game\Repositories\SqlServer97d99iCharacterResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterServiceRepository;
use App\Game\Repositories\SqlServer97d99iCharacterStatAllocationRepository;
use App\Game\Repositories\SqlServer97d99iCharacterStatResetRepository;
use App\Game\Repositories\SqlServer97d99iCharacterVipRepository;
use App\Game\Repositories\SqlServer97d99iItemSerialRepository;
use App\Game\Repositories\SqlServer97d99iItemStorageRepository;
use App\Game\Repositories\SqlServer97d99iMarketItemEscrowRepository;
use App\Game\Repositories\SqlServer97d99iModerationRepository;
use App\Game\Repositories\SqlServer97d99iRealmReadRepository;
use App\Game\Repositories\SqlServer97d99iZenBankRepository;
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
 * Defines the verified DarksTeam 97d+99i classes, stats and SQL adapters.
 */
final class Version97d99iProfile implements GameDatabaseProfile, GameVersionProfile
{
    /**
     * Returns the minimum DarksTeam 97d+99i schema consumed by its adapters.
     *
     * @return array<string, list<string>>
     */
    public function requiredDatabaseSchema(): array
    {
        return [
            'MEMB_INFO' => ['memb_guid', 'memb___id', 'memb__pwd', 'mail_addr', 'bloc_code', 'ctl1_code'],
            'Character' => ['AccountID', 'Name', 'cLevel', 'Class', 'Money', 'Resets', 'GrandResets', 'MarryName'],
            'warehouse' => ['AccountID', 'Items', 'Money'],
            'MEMB_STAT' => ['memb___id', 'ConnectStat', 'ConnectTM', 'DisConnectTM'],
            'AccountCharacter' => ['Id', 'GameID1', 'GameID2', 'GameID3', 'GameID4', 'GameID5', 'GameIDC'],
            'Guild' => ['G_Name', 'G_Master', 'G_Score'],
            'GuildMember' => ['Name', 'G_Name'],
            'OptionData' => ['Name'],
            'GameServerInfo' => ['ItemCount'],
            'VI_CURR_INFO' => ['memb___id', 'memb_guid'],
        ];
    }

    /**
     * Returns only the SQL permissions used by DarksTeam 97d+99i adapters.
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
            'dbo.warehouse' => ['SELECT', 'UPDATE'],
            'dbo.Guild' => ['SELECT', 'UPDATE'],
            'dbo.GuildMember' => ['SELECT', 'UPDATE'],
            'dbo.OptionData' => ['SELECT', 'UPDATE'],
            'dbo.VI_CURR_INFO' => ['SELECT', 'INSERT'],
            'dbo.GameServerInfo' => ['SELECT'],
            'dbo.WZ_GetItemSerial' => ['EXECUTE', 'VIEW DEFINITION'],
        ];
    }

    /**
     * Returns the optional schema features implemented by DarksTeam 97d+99i.
     */
    public function capabilities(): GameVersionCapabilities
    {
        return new GameVersionCapabilities(
            grandResets: true,
            characterVip: true,
            billingRecord: true,
        );
    }

    /**
     * Returns the stable profile key used by environment configuration.
     */
    public function key(): string
    {
        return 'darksteam_97d99i';
    }

    /**
     * Returns every class evolution supported by DarksTeam 97d+99i.
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
     * Returns all four playable 97d+99i class families used by shop filters.
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
        ];
    }

    /**
     * Returns the four attributes stored by the 97d+99i Character table.
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
        ];
    }

    /**
     * Returns all four attributes for every supported 97d+99i class.
     *
     * @return list<CharacterStatDefinition>
     */
    public function statsForClass(int $classCode): array
    {
        if ($this->classForCode($classCode) === null) {
            throw new InvalidArgumentException(
                sprintf('Unsupported 97d+99i character class code: %d.', $classCode),
            );
        }

        return $this->stats();
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
            default => throw new InvalidArgumentException(
                sprintf('Unsupported 97d+99i character class code: %d.', $classCode),
            ),
        };
    }

    /**
     * Returns item services verified for DarksTeam 97d+99i.
     *
     * @return array<class-string, class-string>
     */
    public function itemServiceBindings(): array
    {
        return [
            ItemCatalog::class => ItemCatalog97d99i::class,
            ItemCodec::class => ItemCodec97d99i::class,
            ItemGrantRules::class => ItemGrantRules97d99i::class,
            ItemGeneratorCatalog::class => ItemGeneratorCatalog97d99i::class,
        ];
    }

    /**
     * Returns every SQL adapter selected as one atomic version bundle.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array
    {
        return [
            RealmReadRepository::class => SqlServer97d99iRealmReadRepository::class,
            AccountActivationRepository::class => SqlServer97d99iAccountActivationRepository::class,
            AccountCredentialRepository::class => SqlServer97d99iAccountCredentialRepository::class,
            AccountPasswordRecoveryRepository::class => SqlServer97d99iAccountPasswordRecoveryRepository::class,
            AccountRegistrationRepository::class => SqlServer97d99iAccountRegistrationRepository::class,
            AccountReadRepository::class => SqlServer97d99iAccountReadRepository::class,
            CharacterResetRepository::class => SqlServer97d99iCharacterResetRepository::class,
            CharacterGrandResetRepository::class => SqlServer97d99iCharacterGrandResetRepository::class,
            CharacterMoveRepository::class => SqlServer97d99iCharacterMoveRepository::class,
            CharacterPkClearRepository::class => SqlServer97d99iCharacterPkClearRepository::class,
            CharacterStatAllocationRepository::class => SqlServer97d99iCharacterStatAllocationRepository::class,
            CharacterStatResetRepository::class => SqlServer97d99iCharacterStatResetRepository::class,
            CharacterServiceRepository::class => SqlServer97d99iCharacterServiceRepository::class,
            CharacterVipRepository::class => SqlServer97d99iCharacterVipRepository::class,
            CharacterInventoryEscrowRepository::class => SqlServer97d99iCharacterInventoryEscrowRepository::class,
            ItemStorageRepository::class => SqlServer97d99iItemStorageRepository::class,
            ItemEscrowRepository::class => SqlServer97d99iMarketItemEscrowRepository::class,
            ItemSerialRepository::class => SqlServer97d99iItemSerialRepository::class,
            MarketItemEscrowRepository::class => SqlServer97d99iMarketItemEscrowRepository::class,
            ModerationRepository::class => SqlServer97d99iModerationRepository::class,
            OnlineAccountRepository::class => SqlServerOnlineAccountRepository::class,
            ZenBankRepository::class => SqlServer97d99iZenBankRepository::class,
        ];
    }
}
