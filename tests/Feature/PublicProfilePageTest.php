<?php

namespace Tests\Feature;

use App\Game\Contracts\GensMembershipRepository;
use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Data\GensFamily;
use App\Game\Data\GensMembership;
use App\Game\Data\MasterProgression;
use Tests\TestCase;

/**
 * Verifies public character and guild profiles, links and not-found behavior.
 */
final class PublicProfilePageTest extends TestCase
{
    /**
     * Confirms a guest can inspect a privacy-safe fixture character profile.
     */
    public function test_guest_can_open_public_character_profile(): void
    {
        $response = $this->get(route('characters.show', 'KoPeH'));

        $response
            ->assertOk()
            ->assertSee('KoPeH')
            ->assertSee('Blade Knight')
            ->assertSee('Lorencia')
            ->assertSee('Legends')
            ->assertDontSee('AccountID')
            ->assertDontSee('Inventory');
    }

    /**
     * Confirms standard Season 6 progression appears without exposing blobs.
     */
    public function test_public_character_profile_displays_master_and_gens_progression(): void
    {
        $masterRepository = $this->mock(MasterProgressionRepository::class);
        $masterRepository
            ->shouldReceive('forCharacter')
            ->once()
            ->with('KoPeH')
            ->andReturn(new MasterProgression('KoPeH', 77, 8, 1_234_567));
        $gensRepository = $this->mock(GensMembershipRepository::class);
        $gensRepository
            ->shouldReceive('forCharacter')
            ->once()
            ->with('KoPeH')
            ->andReturn(new GensMembership('KoPeH', GensFamily::Duprian, 15_000, 2));

        $response = $this->get(route('characters.show', 'KoPeH'));

        $response
            ->assertOk()
            ->assertSee('Master level')
            ->assertSee('77')
            ->assertSee('Duprian')
            ->assertSee('15,000')
            ->assertDontSee('MasterSkill');
    }

    /**
     * Confirms a guest can inspect a public guild roster with character links.
     */
    public function test_guest_can_open_public_guild_profile(): void
    {
        $response = $this->get(route('guilds.show', 'Legends'));

        $response
            ->assertOk()
            ->assertSee('Legends')
            ->assertSee('Strength through unity.')
            ->assertSee('KoPeH')
            ->assertSee('Athena')
            ->assertSee('guild-mark-large', false)
            ->assertSee(route('characters.show', 'KoPeH'), false);
    }

    /**
     * Confirms unknown public identities return not found without leaking details.
     */
    public function test_unknown_public_profiles_return_not_found(): void
    {
        $this->get(route('characters.show', 'Unknown'))->assertNotFound();
        $this->get(route('guilds.show', 'UnknownGuild'))->assertNotFound();
    }

    /**
     * Confirms character and guild ranking payloads link to public profiles.
     */
    public function test_rankings_include_public_profile_links(): void
    {
        $response = $this->get(route('rankings'));
        $guildResponse = $this->get(route('rankings', ['board' => 'guilds']));

        $response
            ->assertOk()
            ->assertViewHas('rankingPage', function (array $rankingPage): bool {
                return $rankingPage['items'][0]['profile_url'] === route('characters.show', 'KoPeH');
            });
        $guildResponse
            ->assertOk()
            ->assertViewHas('rankingPage', function (array $rankingPage): bool {
                return $rankingPage['items'][0]['profile_url'] === route('guilds.show', 'Legends');
            });
    }

    /**
     * Confirms both public profile surfaces use Bulgarian interface strings.
     */
    public function test_public_profiles_support_bulgarian_interface(): void
    {
        $this->withSession(['locale' => 'bg'])
            ->get(route('characters.show', 'KoPeH'))
            ->assertOk()
            ->assertSee('Назад към класациите')
            ->assertSee('Локация');

        $this->withSession(['locale' => 'bg'])
            ->get(route('guilds.show', 'Legends'))
            ->assertOk()
            ->assertSee('Членове на гилдията');
    }
}
