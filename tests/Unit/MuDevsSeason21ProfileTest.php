<?php

namespace Tests\Unit;

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
            array_column($profile->classes(), 'code'),
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
     * Confirms imported landscape and full-body portraits have deliberate focal points.
     */
    public function test_preserves_portrait_focal_points_for_new_families(): void
    {
        $profile = new MuDevsSeason21Profile;

        self::assertSame('Slayer', $profile->classForCode(144)?->name);
        self::assertSame('75% 15%', $profile->classForCode(144)?->imagePosition);
        self::assertSame('50% 35%', $profile->classForCode(240)?->imagePosition);
        self::assertSame('50% 15%', $profile->classForCode(128)?->imagePosition);
        self::assertSame('center top', $profile->classForCode(16)?->imagePosition);
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
