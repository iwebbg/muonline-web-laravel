<?php

namespace Tests\Unit;

use App\Game\Data\AccountCharacter;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\CharacterStatDefinition;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\GameVersionCapabilities;
use App\Game\Versions\ShopClassScopeDefinition;
use App\Reset\ResetProfileSettings;
use App\StatReset\Actions\PreviewCharacterStatReset;
use App\Stats\Actions\PreviewCharacterStatAllocation;
use App\Stats\CharacterStats;
use App\Stats\StatAllocation;
use Tests\TestCase;

/**
 * Proves new classes, evolution tiers and stats do not require core edits.
 */
final class GameVersionProfileExtensibilityTest extends TestCase
{
    /**
     * Confirms a fifth Dark Lord stat flows through shared allocation logic.
     */
    public function test_profile_defined_command_stat_is_allocated_dynamically(): void
    {
        $profile = $this->seasonProfile();
        $preview = (new PreviewCharacterStatAllocation($profile))->handle(
            character: $this->darkLord(),
            allocation: StatAllocation::fromArray([
                'strength' => 0,
                'agility' => 0,
                'vitality' => 0,
                'energy' => 0,
                'command' => 10,
            ]),
            settings: ResetProfileSettings::defaults(),
        );

        $this->assertTrue($preview->eligible);
        $this->assertSame(35, $preview->resultingStats->toArray()['command']);
        $this->assertSame(10, $preview->allocatedPoints);
    }

    /**
     * Confirms profile-owned base stats include new attributes during stat reset.
     */
    public function test_profile_defined_base_stats_drive_dynamic_stat_reset(): void
    {
        $profile = $this->seasonProfile();
        $preview = (new PreviewCharacterStatReset($profile))->handle(
            character: $this->darkLord(),
            settings: ResetProfileSettings::defaults(),
        );

        $this->assertTrue($preview->eligible);
        $this->assertSame(25, $preview->refundedPoints);
        $this->assertSame(0, $preview->resultingStats->toArray()['command']);
        $this->assertSame(3, $profile->classForCode(66)?->evolutionTier);
    }

    /**
     * Creates a portable third-evolution Dark Lord fixture.
     */
    private function darkLord(): AccountCharacter
    {
        return new AccountCharacter(
            name: 'LordTest',
            className: 'Lord Emperor',
            classCode: 66,
            image: '/images/characters/dark-lord.png',
            level: 400,
            resets: 10,
            grandResets: 0,
            zen: 100_000_000,
            points: 500,
            strength: 26,
            agility: 20,
            vitality: 20,
            energy: 15,
            map: 'Lorencia',
            online: false,
            additionalStats: ['command' => 25],
        );
    }

    /**
     * Returns a minimal future-season profile implemented outside shared logic.
     */
    private function seasonProfile(): GameVersionProfile
    {
        return new class implements GameVersionProfile
        {
            /**
             * Returns the optional capabilities needed by the isolated test profile.
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
             * Returns the isolated test profile key.
             */
            public function key(): string
            {
                return 'test_season';
            }

            /**
             * Returns one third-evolution Dark Lord definition.
             *
             * @return list<CharacterClassDefinition>
             */
            public function classes(): array
            {
                return [
                    new CharacterClassDefinition(
                        code: 66,
                        key: 'le',
                        abbreviation: 'LE',
                        name: 'Lord Emperor',
                        image: '/images/characters/dark-lord.png',
                        family: 'dark-lord',
                        evolutionTier: 3,
                        previousClassCode: 65,
                    ),
                ];
            }

            /**
             * Resolves the test class by code.
             */
            public function classForCode(int $classCode): ?CharacterClassDefinition
            {
                return $classCode === 66 ? $this->classes()[0] : null;
            }

            /**
             * Resolves the test class by filter key.
             */
            public function classForKey(string $classKey): ?CharacterClassDefinition
            {
                return $classKey === 'le' ? $this->classes()[0] : null;
            }

            /**
             * Returns the version-owned shop class filter.
             *
             * @return list<ShopClassScopeDefinition>
             */
            public function shopClassScopes(): array
            {
                return [new ShopClassScopeDefinition('dl_le', 'DL / LE')];
            }

            /**
             * Returns five attributes including Dark Lord Command.
             *
             * @return list<CharacterStatDefinition>
             */
            public function stats(): array
            {
                return [
                    new CharacterStatDefinition('strength', 'Strength', 'Сила', 'Strength', 0, 65_535),
                    new CharacterStatDefinition('agility', 'Agility', 'Ловкост', 'Dexterity', 0, 65_535),
                    new CharacterStatDefinition('vitality', 'Vitality', 'Жизненост', 'Vitality', 0, 65_535),
                    new CharacterStatDefinition('energy', 'Energy', 'Енергия', 'Energy', 0, 65_535),
                    new CharacterStatDefinition('command', 'Command', 'Команда', 'Leadership', 0, 65_535),
                ];
            }

            /**
             * Returns the five attributes available to the Dark Lord family.
             *
             * @return list<CharacterStatDefinition>
             */
            public function statsForClass(int $classCode): array
            {
                return $classCode === 66 ? $this->stats() : [];
            }

            /**
             * Returns level-one Dark Lord attributes including Command.
             */
            public function baseStatsForClass(int $classCode): CharacterStats
            {
                return CharacterStats::fromArray([
                    'strength' => 26,
                    'agility' => 20,
                    'vitality' => 20,
                    'energy' => 15,
                    'command' => 0,
                ]);
            }

            /**
             * Returns no persistence adapters for the isolated calculator test.
             *
             * @return array<class-string, class-string>
             */
            public function repositoryBindings(): array
            {
                return [];
            }
        };
    }
}
