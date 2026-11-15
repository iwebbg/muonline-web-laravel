<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Contracts\AccountActivationRepository;
use App\Game\Contracts\AccountCredentialRepository;
use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\AccountRegistrationRepository;
use App\Game\Contracts\BattleCoreReadRepository;
use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\CharacterMoveRepository;
use App\Game\Contracts\CharacterPkClearRepository;
use App\Game\Contracts\CharacterResetRepository;
use App\Game\Contracts\CharacterStatAllocationRepository;
use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Contracts\GameCurrencyBalanceRepository;
use App\Game\Contracts\GensMembershipRepository;
use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Contracts\LuckyCoinBalanceRepository;
use App\Game\Contracts\MapNameLookup;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Contracts\ModerationRepository;
use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountActivationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountCredentialRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountRegistrationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21BattleCoreReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CastleSiegeReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterGrandResetRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterInventoryEscrowRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterMoveRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterPkClearRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterResetRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterStatAllocationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterStatResetRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CurrencyBalanceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21GensMembershipRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21ItemSerialRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21ItemStorageRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21LuckyCoinBalanceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MarketItemEscrowRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MasterProgressionRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21ModerationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21RealmReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21ZenBankRepository;
use App\Game\Repositories\SqlServerOnlineAccountRepository;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\CharacterStatDefinition;
use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Game\Versions\Contracts\GameDatabaseSchemaBootstrap;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\GameVersionCapabilities;
use App\Game\Versions\ShopClassScopeDefinition;
use App\Game\Versions\UnavailableItemGeneratorCatalog;
use App\Modules\ServerModule;
use App\Reset\InventoryPolicy;
use App\Stats\CharacterStats;
use InvalidArgumentException;
use RuntimeException;

/**
 * Selects the reviewed native account, character and item services for MUDevs S21.1-2.
 *
 * Route and module allow-lists keep incomplete systems unavailable. Live write
 * commissioning remains separate from enabling the reviewed account tools.
 */
final class MuDevsSeason21Profile implements GameDatabaseProfile, GameDatabaseSchemaBootstrap, GameVersionProfile
{
    private const SCHEMA_EXTENSION_RESOURCE = 'game/mudevs_season_21/schema-extensions.sql';

    /** @var list<CharacterClassDefinition>|null */
    private ?array $resolvedClasses = null;

    /**
     * Returns the stable identity reserved for this emulator distribution.
     */
    public function key(): string
    {
        return 'mudevs_season_21';
    }

    /**
     * Returns reviewed optional systems and the staged website availability.
     */
    public function capabilities(): GameVersionCapabilities
    {
        return new GameVersionCapabilities(
            grandResets: true,
            characterVip: false,
            billingRecord: false,
            itemGenerator: false,
            masterProgression: true,
            castleSiege: true,
            gens: true,
            luckyCoin: true,
            readOnly: false,
            schemaExtensionResource: self::SCHEMA_EXTENSION_RESOURCE,
            resetInventoryPolicies: [InventoryPolicy::KeepAll],
            availableModules: [
                ServerModule::Registration,
                ServerModule::PasswordRecovery,
                ServerModule::AccountSecurity,
                ServerModule::ZenBank,
                ServerModule::Credits,
                ServerModule::VoteRewards,
                ServerModule::WebStorage,
                ServerModule::JewelBank,
                ServerModule::Market,
                ServerModule::Auction,
                ServerModule::WebShop,
                ServerModule::MysteryBoxes,
                ServerModule::Rewards,
            ],
            availableRoutes: [
                'home', 'ready', 'server.status', 'rankings', 'rankings.*',
                'characters.*', 'guilds.*', 'news.*', 'guides.*', 'pages.*',
                'register', 'register.store', 'password.*', 'account.activation*',
                'account.security.index', 'account.credentials.update', 'account.deletion-requests.*',
                'account.zen-bank.*', 'account.web-storage.*', 'account.jewel-bank.*',
                'account.credits.index', 'account.credit-item-exchanges.store', 'account.online-time-claims.store',
                'account.credit-packages.purchase', 'account.credit-payments.return', 'payments.webhooks',
                'market.*', 'shop.items.*', 'shop.mystery-boxes.*',
                'account.rewards.*', 'account.votes.*',
                'account.reset-preview', 'account.character-reset', 'account.grand-reset-preview',
                'account.character-grand-reset', 'account.stat-allocation-preview', 'account.stat-allocations.store',
                'account.stat-reset-preview', 'account.character-stat-reset', 'account.pk-clear-preview',
                'account.character-pk-clear', 'account.move-to-town-preview', 'account.character-move',
                'downloads.*', 'login', 'login.store', 'logout', 'account.dashboard', 'account.item-storage', 'locale.update',
                'admin.login', 'admin.login.store', 'admin.logout', 'admin.dashboard', 'admin.audit-log',
                'admin.character-rules.*', 'admin.moderation*', 'admin.game-masters.*', 'admin.modules.*',
                'admin.s21-warehouse', 'admin.event-schedules.*', 'admin.item-grants.*',
                'admin.news-posts.*', 'admin.downloads.*', 'admin.website-settings.*',
                'admin.game-servers.*', 'admin.wallet.*', 'admin.economy-settings.*',
                'admin.credit-exchange-offers.*', 'admin.credit-packages.*', 'admin.credit-payments.*',
                'admin.vote-sites.*', 'admin.vote-claims.*', 'admin.market.*', 'admin.shop-offers.*',
                'admin.economy-recovery.*',
            ],
        );
    }

    /**
     * Combines SQL-seeded families with the supplied client's advancement names.
     *
     * @return list<CharacterClassDefinition>
     */
    public function classes(): array
    {
        if ($this->resolvedClasses !== null) {
            return $this->resolvedClasses;
        }

        $definitions = [];

        foreach ($this->baseClasses() as $definition) {
            $definitions[$definition->code] = $definition;
        }

        $path = dirname(__DIR__, 4).'/resources/game/mudevs_season_21/class-advancements.json';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The MUDevs S21 class advancement catalog could not be read.');
        }

        /** @var list<array{code: int, baseCode: int, tier: int, previousCode: int, name: string, key: string, textIndex: int}> $advancements */
        $advancements = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        foreach ($advancements as $advancement) {
            $base = $definitions[$advancement['baseCode']] ?? null;

            if ($base === null || $base->evolutionTier !== 1
                || $advancement['tier'] < 2 || $advancement['tier'] > 5
                || $advancement['code'] !== $base->code + $advancement['tier'] - 1
                || isset($definitions[$advancement['code']])
                || ! isset($definitions[$advancement['previousCode']])) {
                throw new RuntimeException('The MUDevs S21 class advancement catalog contains an invalid transition.');
            }

            $definitions[$advancement['code']] = new CharacterClassDefinition(
                code: $advancement['code'],
                key: $advancement['key'],
                abbreviation: $advancement['code'] === 18 ? 'BM' : $base->abbreviation.' '.$advancement['tier'],
                name: $advancement['name'],
                image: $base->image,
                family: $base->family,
                evolutionTier: $advancement['tier'],
                previousClassCode: $advancement['previousCode'],
                imagePosition: $base->imagePosition,
            );
        }

        ksort($definitions);
        $this->resolvedClasses = array_values($definitions);

        return $this->resolvedClasses;
    }

    /**
     * Keeps the original portraits and identifiers for the sixteen SQL-seeded families.
     *
     * @return list<CharacterClassDefinition>
     */
    private function baseClasses(): array
    {
        return [
            new CharacterClassDefinition(
                code: 0,
                key: 'dw',
                abbreviation: 'DW',
                name: 'Dark Wizard',
                image: '/images/characters/soul-master.jpg',
                family: 'wizard',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 16,
                key: 'dk',
                abbreviation: 'DK',
                name: 'Dark Knight',
                image: '/images/characters/blade-knight.png',
                family: 'knight',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 32,
                key: 'fe',
                abbreviation: 'FE',
                name: 'Fairy Elf',
                image: '/images/characters/muse-elf.png',
                family: 'elf',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 48,
                key: 'mg',
                abbreviation: 'MG',
                name: 'Magic Gladiator',
                image: '/images/characters/magic-gladiator.png',
                family: 'magic-gladiator',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 64,
                key: 'dl',
                abbreviation: 'DL',
                name: 'Dark Lord',
                image: '/images/characters/dark-lord.png',
                family: 'dark-lord',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 80,
                key: 'su',
                abbreviation: 'SU',
                name: 'Summoner',
                image: '/images/characters/summoner.jpg',
                family: 'summoner',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 96,
                key: 'rf',
                abbreviation: 'RF',
                name: 'Rage Fighter',
                image: '/images/characters/rage-fighter.jpg',
                family: 'rage-fighter',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 112,
                key: 'gl',
                abbreviation: 'GL',
                name: 'Grow Lancer',
                image: '/images/characters/mudevs_season_21/grow-lancer.webp',
                family: 'grow-lancer',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 128,
                key: 'rw',
                abbreviation: 'RW',
                name: 'Rune Wizard',
                image: '/images/characters/mudevs_season_21/rune-wizard.jpeg',
                family: 'rune-wizard',
                evolutionTier: 1,
                imagePosition: '50% 15%',
            ),
            new CharacterClassDefinition(
                code: 144,
                key: 'sl',
                abbreviation: 'SL',
                name: 'Slayer',
                image: '/images/characters/mudevs_season_21/slayer.png',
                family: 'slayer',
                evolutionTier: 1,
                imagePosition: '75% 15%',
            ),
            new CharacterClassDefinition(
                code: 160,
                key: 'gc',
                abbreviation: 'GC',
                name: 'Gun Crusher',
                image: '/images/characters/mudevs_season_21/gun-crusher.jpeg',
                family: 'gun-crusher',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 176,
                key: 'km',
                abbreviation: 'KM',
                name: 'Kundun Mephis',
                image: '/images/characters/mudevs_season_21/kundun-mephis.webp',
                family: 'kundun-mephis',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 192,
                key: 'lm',
                abbreviation: 'LM',
                name: 'Lemuria',
                image: '/images/characters/mudevs_season_21/lemuria.jpeg',
                family: 'lemuria',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 208,
                key: 'ik',
                abbreviation: 'IK',
                name: 'Illusion Knight',
                image: '/images/characters/mudevs_season_21/illusion-knight.jpeg',
                family: 'illusion-knight',
                evolutionTier: 1,
                imagePosition: '50% 20%',
            ),
            new CharacterClassDefinition(
                code: 224,
                key: 'al',
                abbreviation: 'AL',
                name: 'Alchemist',
                image: '/images/characters/mudevs_season_21/alchemist.webp',
                family: 'alchemist',
                evolutionTier: 1,
            ),
            new CharacterClassDefinition(
                code: 240,
                key: 'cs',
                abbreviation: 'CS',
                name: 'Crusader',
                image: '/images/characters/mudevs_season_21/crusader.jpeg',
                family: 'crusader',
                evolutionTier: 1,
                imagePosition: '50% 35%',
            ),
        ];
    }

    /**
     * Resolves only codes represented by the reviewed class catalog.
     */
    public function classForCode(int $classCode): ?CharacterClassDefinition
    {
        foreach ($this->classes() as $definition) {
            if ($definition->code === $classCode) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Resolves one profile-owned ranking filter key.
     */
    public function classForKey(string $classKey): ?CharacterClassDefinition
    {
        foreach ($this->classes() as $definition) {
            if ($definition->key === $classKey) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Provides every native family for future version-aware shop audiences.
     *
     * @return list<ShopClassScopeDefinition>
     */
    public function shopClassScopes(): array
    {
        $scopes = ['all' => new ShopClassScopeDefinition('all', 'All classes')];

        $legacyScopes = ['wizard' => 'dw_sm', 'knight' => 'dk_bk', 'elf' => 'elf_me', 'magic-gladiator' => 'mg', 'dark-lord' => 'dl_le', 'summoner' => 'su_bs', 'rage-fighter' => 'rf_fm'];

        foreach ($this->classes() as $definition) {
            if (! isset($scopes[$definition->family])) {
                $scopes[$definition->family] = new ShopClassScopeDefinition($legacyScopes[$definition->family] ?? $definition->family, $definition->name);
            }
        }

        return array_values($scopes);
    }

    /**
     * Returns stat mappings and the 65,000 limit configured for all account levels.
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
     * Exposes Leadership only to a recognized Dark Lord class.
     *
     * @return list<CharacterStatDefinition>
     */
    public function statsForClass(int $classCode): array
    {
        $definition = $this->classForCode($classCode);

        if ($definition === null) {
            throw new InvalidArgumentException('Unsupported MUDevs S21 character class code.');
        }

        if ($definition->family === 'dark-lord') {
            return $this->stats();
        }

        return array_values(array_filter(
            $this->stats(),
            static fn (CharacterStatDefinition $stat): bool => $stat->key !== 'command',
        ));
    }

    /**
     * Resolves base stats only after validating a source-backed persisted class code.
     */
    public function baseStatsForClass(int $classCode): CharacterStats
    {
        if ($this->classForCode($classCode) === null) {
            throw new InvalidArgumentException('Unsupported MUDevs S21 character class code.');
        }

        return ClassFamilyMuDevsSeason21::from(intdiv($classCode, 16))->baseStats();
    }

    /**
     * Prevents generated-item sales before S21 item encoding and pricing are verified.
     *
     * @return array<class-string, class-string>
     */
    public function itemServiceBindings(): array
    {
        return [
            ItemCodec::class => ItemCodecAdapterMuDevsSeason21::class,
            ItemCatalog::class => ItemCatalogAdapterMuDevsSeason21::class,
            ItemGrantRules::class => ItemGrantRulesMuDevsSeason21::class,
            ItemGeneratorCatalog::class => UnavailableItemGeneratorCatalog::class,
            MapNameLookup::class => MapNameMapperMuDevsSeason21::class,
        ];
    }

    /**
     * Binds only native reads already implemented for this commissioning bundle.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array
    {
        return [
            AccountRegistrationRepository::class => SqlServerMuDevsSeason21AccountRegistrationRepository::class,
            AccountActivationRepository::class => SqlServerMuDevsSeason21AccountActivationRepository::class,
            AccountCredentialRepository::class => SqlServerMuDevsSeason21AccountCredentialRepository::class,
            AccountPasswordRecoveryRepository::class => SqlServerMuDevsSeason21AccountPasswordRecoveryRepository::class,
            CharacterResetRepository::class => SqlServerMuDevsSeason21CharacterResetRepository::class,
            CharacterGrandResetRepository::class => SqlServerMuDevsSeason21CharacterGrandResetRepository::class,
            CharacterStatAllocationRepository::class => SqlServerMuDevsSeason21CharacterStatAllocationRepository::class,
            CharacterStatResetRepository::class => SqlServerMuDevsSeason21CharacterStatResetRepository::class,
            CharacterPkClearRepository::class => SqlServerMuDevsSeason21CharacterPkClearRepository::class,
            CharacterMoveRepository::class => SqlServerMuDevsSeason21CharacterMoveRepository::class,
            ModerationRepository::class => SqlServerMuDevsSeason21ModerationRepository::class,
            ZenBankRepository::class => SqlServerMuDevsSeason21ZenBankRepository::class,
            LuckyCoinBalanceRepository::class => SqlServerMuDevsSeason21LuckyCoinBalanceRepository::class,
            GensMembershipRepository::class => SqlServerMuDevsSeason21GensMembershipRepository::class,
            CharacterInventoryEscrowRepository::class => SqlServerMuDevsSeason21CharacterInventoryEscrowRepository::class,
            ItemSerialRepository::class => SqlServerMuDevsSeason21ItemSerialRepository::class,
            ItemEscrowRepository::class => SqlServerMuDevsSeason21MarketItemEscrowRepository::class,
            MarketItemEscrowRepository::class => SqlServerMuDevsSeason21MarketItemEscrowRepository::class,
            ItemStorageRepository::class => SqlServerMuDevsSeason21ItemStorageRepository::class,
            BattleCoreReadRepository::class => SqlServerMuDevsSeason21BattleCoreReadRepository::class,
            CastleSiegeReadRepository::class => SqlServerMuDevsSeason21CastleSiegeReadRepository::class,
            AccountReadRepository::class => SqlServerMuDevsSeason21AccountReadRepository::class,
            RealmReadRepository::class => SqlServerMuDevsSeason21RealmReadRepository::class,
            MasterProgressionRepository::class => SqlServerMuDevsSeason21MasterProgressionRepository::class,
            GameCurrencyBalanceRepository::class => SqlServerMuDevsSeason21CurrencyBalanceRepository::class,
            OnlineAccountRepository::class => SqlServerOnlineAccountRepository::class,
        ];
    }

    /**
     * Prepares native custody receipts after runtime principals exist and before object grants.
     */
    public function bootstrapSchemaExtensionResource(): string
    {
        return self::SCHEMA_EXTENSION_RESOURCE;
    }

    /**
     * Reuses the read-only commissioning schema instead of importing legacy S6 fields.
     *
     * @return array<string, list<string>>
     */
    public function requiredDatabaseSchema(): array
    {
        return (new ReadOnlyDatabaseProfileMuDevsSeason21)->requiredDatabaseSchema();
    }

    /**
     * Retains SELECT-only game access while the version bundle is under development.
     *
     * @return array<string, list<string>>
     */
    public function databaseObjectPermissions(): array
    {
        return (new ReadOnlyDatabaseProfileMuDevsSeason21)->databaseObjectPermissions();
    }
}
