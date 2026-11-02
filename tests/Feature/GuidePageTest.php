<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Content\NewsPostType;
use App\Models\Administrator;
use App\Models\NewsPost;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies guide publication boundaries, fallback content and administration.
 */
final class GuidePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the guide library includes guides but never cross-lists news.
     */
    public function test_guide_library_is_separate_from_realm_news(): void
    {
        NewsPost::factory()->published()->create([
            'slug' => 'realm-news',
            'title_en' => 'Realm news only',
        ]);
        NewsPost::factory()->guide()->published()->create([
            'slug' => 'reset-guide',
            'title_en' => 'Reset guide',
        ]);

        $this->get('/guides')
            ->assertOk()
            ->assertSee('Reset guide')
            ->assertDontSee('Realm news only');
        $this->get('/news')
            ->assertOk()
            ->assertSee('Realm news only')
            ->assertDontSee('Reset guide');
        $this->get('/guides/reset-guide')->assertOk();
        $this->get('/news/reset-guide')->assertNotFound();
    }

    /**
     * Confirms guides use English field fallback and escape stored markup.
     */
    public function test_bulgarian_guide_uses_safe_english_fallback(): void
    {
        NewsPost::factory()->guide()->published()->create([
            'slug' => 'safe-guide',
            'title_en' => 'English guide title',
            'title_bg' => 'Българско ръководство',
            'excerpt_en' => 'English guide summary.',
            'excerpt_bg' => null,
            'body_en' => '<script>alert("guide-xss")</script>',
            'body_bg' => null,
        ]);

        $response = $this
            ->withSession(['locale' => 'bg'])
            ->get('/guides/safe-guide');

        $response
            ->assertOk()
            ->assertSee('Българско ръководство')
            ->assertSee('English guide summary.')
            ->assertDontSee('<script>alert("guide-xss")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;guide-xss&quot;)&lt;/script&gt;', false);
    }

    /**
     * Confirms a content manager can publish a guide through the audited editor.
     */
    public function test_content_manager_can_create_a_guide(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageContent])
            ->create();
        $csrfToken = 'guide-content-manager-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/content/news', [
                '_token' => $csrfToken,
                'slug' => 'starter-guide',
                'type' => 'guide',
                'title_en' => 'Starter guide',
                'title_bg' => null,
                'excerpt_en' => 'Everything needed to begin.',
                'excerpt_bg' => null,
                'body_en' => 'Create a character and enter Lorencia.',
                'body_bg' => null,
                'status' => 'published',
                'published_at' => null,
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $post = NewsPost::query()->sole();

        $this->assertSame(NewsPostType::Guide, $post->type);
        $this->get('/guides/starter-guide')->assertOk()->assertSee('Starter guide');
    }
}
