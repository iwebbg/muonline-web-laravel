<?php

namespace Tests\Feature;

use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\MasterProgression;
use App\Game\Data\PublicCharacterProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies portrait focal points and optional progression fields reach public views.
 */
final class CharacterPortraitPositionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms custom focal points and enhancement points render on public profiles.
     */
    public function test_renders_a_profile_focal_point_and_enhancement_points(): void
    {
        $this->app->setLocale('en');
        $this->mock(RealmReadRepository::class)
            ->shouldReceive('publicCharacter')
            ->once()
            ->with('Portrait')
            ->andReturn(new PublicCharacterProfile(
                name: 'Portrait', className: 'Slayer',
                image: '/images/characters/muonline-web-slayer.png',
                level: 800, resets: 0, grandResets: 0, map: 'Aquilas Temple',
                pkLevel: 0, guildName: null, online: false, vip: false,
                imagePosition: '75% 15%',
            ));
        $this->mock(MasterProgressionRepository::class)
            ->shouldReceive('forCharacter')
            ->once()
            ->with('Portrait')
            ->andReturn(new MasterProgression('Portrait', 400, 10, 9_000_000_000, 23));

        $this->get('/characters/Portrait')
            ->assertOk()
            ->assertSee('object-position: 75% 15%', false)
            ->assertSee('Available Enhancement Points')
            ->assertSee('23');
    }

    /**
     * Confirms existing ranking consumers keep their default serialized field shape.
     */
    public function test_legacy_ranking_entries_omit_default_portrait_metadata(): void
    {
        $entry = new CharacterRankingEntry(
            name: 'Legacy', classCode: 16, className: 'Dark Knight',
            classAbbreviation: 'DK', image: '/images/characters/muonline-web-dark-knight.png',
            level: 1, resets: 0, grandResets: 0, pkCount: 0, pkLevel: 0, online: false,
        );

        self::assertArrayNotHasKey('imagePosition', $entry->toArray());
        self::assertSame('center top', $entry->imagePosition);
    }
}
