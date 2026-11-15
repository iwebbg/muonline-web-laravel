<?php

namespace Tests\Unit;

use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies source-backed base classes and portraits without inventing advancement codes.
 */
final class MuDevsSeason21ProfileTest extends TestCase
{
    /**
     * Confirms each SQL-seeded base class resolves by code and ranking key.
     */
    public function test_resolves_all_sixteen_sql_seeded_base_classes(): void
    {
        $profile = new MuDevsSeason21Profile;

        self::assertSame('mudevs_season_21', $profile->key());
        self::assertSame(
            [0, 16, 32, 48, 64, 80, 96, 112, 128, 144, 160, 176, 192, 208, 224, 240],
            array_column(array_filter(
                $profile->classes(),
                static fn (CharacterClassDefinition $definition): bool => $definition->evolutionTier === 1,
            ), 'code'),
        );

        foreach ($profile->classes() as $definition) {
            self::assertSame($definition->name, $profile->classForCode($definition->code)?->name);
            self::assertSame($definition->code, $profile->classForKey($definition->key)?->code);
            self::assertFileExists(dirname(__DIR__, 2).'/public'.$definition->image);
        }

        self::assertCount(17, $profile->shopClassScopes());
        self::assertNull($profile->classForCode(255));
        self::assertNull($profile->classForKey('unknown'));
    }

    /**
     * Confirms the shared portrait collection uses centered framing for every native family.
     */
    public function test_shared_portraits_use_centered_framing_for_new_families(): void
    {
        $profile = new MuDevsSeason21Profile;

        self::assertSame('Slayer', $profile->classForCode(144)?->name);
        self::assertSame('center top', $profile->classForCode(144)?->imagePosition);
        self::assertSame('center top', $profile->classForCode(240)?->imagePosition);
        self::assertSame('center top', $profile->classForCode(128)?->imagePosition);
        self::assertSame('center top', $profile->classForCode(16)?->imagePosition);
    }

    /**
     * Resolves runtime class 18 using the owner's confirmed Blade Master title.
     */
    public function test_resolves_the_client_verified_blade_master_advancement(): void
    {
        $profile = new MuDevsSeason21Profile;
        $definition = $profile->classForCode(18);

        self::assertSame('Blade Master', $definition?->name);
        self::assertSame('knight', $definition?->family);
        self::assertSame(3, $definition?->evolutionTier);
        self::assertSame(18, $profile->classForKey('bm')?->code);
        self::assertSame($profile->baseStatsForClass(16)->toArray(), $profile->baseStatsForClass(18)->toArray());
        self::assertSame(['strength', 'agility', 'vitality', 'energy'], array_column($profile->statsForClass(18), 'key'));
        self::assertSame(17, $definition?->previousClassCode);
        self::assertSame('Blade Knight', $profile->classForCode(17)?->name);
    }

    /**
     * Resolves every third, fourth and fifth form while preserving each family's portrait.
     */
    public function test_all_families_include_complete_source_named_advancements(): void
    {
        $profile = new MuDevsSeason21Profile;
        $definitions = $profile->classes();

        self::assertCount(76, $definitions);
        self::assertCount(76, array_unique(array_column($definitions, 'key')));

        foreach (range(0, 15) as $family) {
            $base = $profile->classForCode($family * 16);

            foreach ([3, 4, 5] as $tier) {
                $definition = $profile->classForCode($family * 16 + $tier - 1);
                self::assertNotNull($definition);
                self::assertSame($tier, $definition->evolutionTier);
                self::assertSame($base?->family, $definition->family);
                self::assertSame($base?->image, $definition->image);
                self::assertSame($base?->imagePosition, $definition->imagePosition);
                self::assertSame($definition, $profile->classForKey($definition->key));
                self::assertNotNull($profile->classForCode($definition->previousClassCode));
            }
        }

        self::assertSame('Dragon Knight', $profile->classForCode(19)?->name);
        self::assertSame('Ignition Knight', $profile->classForCode(20)?->name);
        self::assertSame('Alchemist Creator', $profile->classForCode(228)?->name);
        self::assertSame('Templar Commander', $profile->classForCode(244)?->name);

        foreach ([49, 65, 97, 113, 5, 21, 245, 255, -1] as $unsupportedCode) {
            self::assertNull($profile->classForCode($unsupportedCode));
        }
    }

    /**
     * Confirms native stats and Command are resolved through the correct family.
     */
    public function test_maps_native_stats_and_configured_limits_without_exposing_command_to_other_families(): void
    {
        $profile = new MuDevsSeason21Profile;

        self::assertSame(
            ['strength' => 13, 'agility' => 18, 'vitality' => 14, 'energy' => 40],
            $profile->baseStatsForClass(128)->toArray(),
        );
        self::assertSame(25, $profile->baseStatsForClass(64)->toArray()['command']);
        self::assertSame(
            ['strength', 'agility', 'vitality', 'energy'],
            array_column($profile->statsForClass(240), 'key'),
        );
        self::assertSame(
            ['strength', 'agility', 'vitality', 'energy', 'command'],
            array_column($profile->statsForClass(64), 'key'),
        );
        self::assertSame([65_000], array_values(array_unique(array_column($profile->stats(), 'maximum'))));
        self::assertFalse($profile->capabilities()->itemGenerator);
        self::assertFalse($profile->capabilities()->characterVip);
    }

    /**
     * Confirms unverified codes cannot silently receive another family's base stats.
     */
    public function test_rejects_unverified_class_codes_for_stat_operations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MuDevsSeason21Profile)->baseStatsForClass(255);
    }

    /**
     * Confirms private stat reads also reject unknown class identities.
     */
    public function test_rejects_unknown_class_codes_for_stat_definitions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MuDevsSeason21Profile)->statsForClass(255);
    }
}
