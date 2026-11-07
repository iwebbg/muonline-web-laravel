<?php

namespace App\Game\Versions\Version1_0M;

use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\CharacterStatDefinition;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\GameVersionCapabilities;
use App\Game\Versions\ShopClassScopeDefinition;
use App\Stats\CharacterStats;
use InvalidArgumentException;

/**
 * Defines the DarksTeam 1.0M classes, attributes and persistence adapters.
 */
final class Version1_0MProfile implements GameVersionProfile
{
    /**
     * Returns the optional schema features implemented by DarksTeam 1.0M.
     */
    public function capabilities(): GameVersionCapabilities
    {
        return new GameVersionCapabilities(
            grandResets: false,
            characterVip: false,
            billingRecord: false,
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
            new CharacterClassDefinition(65, 'le', 'LE', 'Lord Emperor', '/images/characters/dark-lord.png', 'dark-lord', 2, 64),
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
            new ShopClassScopeDefinition('dl_le', 'DL / LE'),
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
            64, 65 => CharacterStats::fromArray([
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
     * Returns no adapters until the isolated 1.0M persistence set is complete.
     *
     * @return array<class-string, class-string>
     */
    public function repositoryBindings(): array
    {
        return [];
    }
}
