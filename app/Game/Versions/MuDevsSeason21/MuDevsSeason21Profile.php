<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\GameCurrencyBalanceRepository;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CurrencyBalanceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MasterProgressionRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21RealmReadRepository;
use App\Game\Repositories\SqlServerOnlineAccountRepository;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\CharacterStatDefinition;
use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\GameVersionCapabilities;
use App\Game\Versions\ShopClassScopeDefinition;
use App\Game\Versions\UnavailableItemGeneratorCatalog;
use App\Stats\CharacterStats;
use InvalidArgumentException;

/**
 * Defines the verified read-model foundation for the supplied MUDevs S21.1-2 pack.
 *
 * This bundle remains outside the selectable profile registry until native
 * advancement codes, storage and remaining repository bindings are verified.
 */
final class MuDevsSeason21Profile implements GameDatabaseProfile, GameVersionProfile
{
    /**
     * Returns the stable identity reserved for this emulator distribution.
     */
    public function key(): string
    {
        return 'mudevs_season_21';
    }

    /**
     * Returns only optional systems implemented by the current read bundle.
     */
    public function capabilities(): GameVersionCapabilities
    {
        return new GameVersionCapabilities(
            grandResets: true,
            characterVip: false,
            billingRecord: false,
            itemGenerator: false,
            masterProgression: true,
        );
    }

    /**
     * Lists base class codes explicitly seeded by the supplied DefaultClassType SQL.
     *
     * @return list<CharacterClassDefinition>
     */
    public function classes(): array
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

        foreach ($this->classes() as $definition) {
            if (! isset($scopes[$definition->family])) {
                $scopes[$definition->family] = new ShopClassScopeDefinition($definition->family, $definition->name);
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
        return [ItemGeneratorCatalog::class => UnavailableItemGeneratorCatalog::class];
    }

    /**
     * Binds only native reads already implemented for this commissioning bundle.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array
    {
        return [
            AccountReadRepository::class => SqlServerMuDevsSeason21AccountReadRepository::class,
            RealmReadRepository::class => SqlServerMuDevsSeason21RealmReadRepository::class,
            MasterProgressionRepository::class => SqlServerMuDevsSeason21MasterProgressionRepository::class,
            GameCurrencyBalanceRepository::class => SqlServerMuDevsSeason21CurrencyBalanceRepository::class,
            OnlineAccountRepository::class => SqlServerOnlineAccountRepository::class,
        ];
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
