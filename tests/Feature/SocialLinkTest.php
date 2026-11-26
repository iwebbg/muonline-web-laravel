<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use App\Models\Administrator;
use App\Models\SocialLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** Verifies community links, repeated networks, visibility and settings permissions. */
final class SocialLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Keeps the section hidden until two links exist, even on the same network. */
    public function test_home_requires_two_links_and_preserves_channel_order_and_escaped_labels(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('id="community-heading"', false);
        SocialLink::factory()->create(['label' => 'Later', 'sort_order' => 20]);
        $this->get(route('home'))->assertOk()->assertDontSee('id="community-heading"', false);
        SocialLink::factory()->create(['label' => '<script>channel</script>', 'sort_order' => 10]);

        $this->get(route('home'))->assertOk()
            ->assertSee('id="community-heading"', false)
            ->assertSeeInOrder(['&lt;script&gt;channel&lt;/script&gt;', 'Later'], false)
            ->assertDontSee('<script>channel</script>', false)
            ->assertSee('images/social/youtube.svg');
    }

    /** Allows managers to create, update and remove independent destinations. */
    #[TestWith([Version97d99iProfile::class])]
    #[TestWith([Version1_0MProfile::class])]
    #[TestWith([MuEmuSeason6Profile::class])]
    #[TestWith([MuDevsSeason21Profile::class])]
    public function test_settings_manager_can_manage_multiple_links_for_one_network(string $profileClass): void
    {
        $this->app->instance(GameVersionProfile::class, new $profileClass);
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');
        $this->withSession(['_token' => 'social-token']);
        $data = ['_token' => 'social-token', 'type' => 'youtube', 'label' => 'Main', 'url' => 'https://youtube.com/@main', 'sort_order' => 0];
        $this->post(route('admin.social-links.store'), $data)->assertRedirect();
        $this->post(route('admin.social-links.store'), [...$data, 'label' => 'Second'])->assertRedirect();
        $link = SocialLink::query()->orderBy('id')->firstOrFail();

        $this->put(route('admin.social-links.update', $link), [...$data, 'url' => 'https://youtube.com/@new'])->assertRedirect();
        self::assertSame('https://youtube.com/@new', $link->refresh()->url);
        $this->get(route('admin.website-settings.edit'))->assertOk()->assertSee('Social links');
        $this->delete(route('admin.social-links.destroy', $link), ['_token' => 'social-token'])->assertRedirect();
        $this->assertDatabaseMissing('social_links', ['id' => $link->getKey()]);
        $this->assertDatabaseCount('social_links', 1);
    }

    /** Prevents scripts and unsupported platforms from becoming public links. */
    public function test_rejects_unsafe_links_and_unknown_networks(): void
    {
        $this->actingAs(Administrator::factory()->create(), 'admin')
            ->withSession(['_token' => 'social-token'])
            ->post(route('admin.social-links.store'), [
                '_token' => 'social-token', 'type' => 'unknown', 'url' => 'javascript:alert(1)', 'sort_order' => 0,
            ])->assertSessionHasErrors(['type', 'url']);
        $this->assertDatabaseCount('social_links', 0);
    }

    /** Rejects guests and administrators without website settings permission. */
    public function test_social_changes_require_website_settings_permission(): void
    {
        $this->post(route('admin.social-links.store'))->assertRedirect(route('admin.login'));
        $moderator = Administrator::factory()->gameMaster([AdministratorPermission::ModerateAccounts])->create();
        $link = SocialLink::factory()->create();
        $this->actingAs($moderator, 'admin')->withSession(['_token' => 'social-token']);
        $this->post(route('admin.social-links.store'), ['_token' => 'social-token'])->assertForbidden();
        $this->put(route('admin.social-links.update', $link), ['_token' => 'social-token'])->assertForbidden();
        $this->delete(route('admin.social-links.destroy', $link), ['_token' => 'social-token'])->assertForbidden();
        $this->assertDatabaseCount('social_links', 1);
    }
}
