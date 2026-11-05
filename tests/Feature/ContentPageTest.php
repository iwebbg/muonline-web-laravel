<?php

namespace Tests\Feature;

use App\Content\NewsPostType;
use App\Models\GameServer;
use App\Models\NewsPost;
use Database\Seeders\ContentPageSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies standalone content publication, seeded legal pages and footer links.
 */
final class ContentPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the seeder creates real bilingual legal pages without duplicates.
     */
    public function test_legal_page_seeder_is_idempotent_and_pages_are_public(): void
    {
        $this->seed(ContentPageSeeder::class);
        $this->seed(ContentPageSeeder::class);

        $this->assertSame(3, NewsPost::query()->where('type', NewsPostType::Page)->count());

        $this->get('/pages/cookies')
            ->assertOk()
            ->assertSee('Cookie Policy')
            ->assertSee('Session cookie');

        $this->withSession(['locale' => 'bg'])
            ->get('/pages/privacy-policy')
            ->assertOk()
            ->assertSee('Политика за поверителност')
            ->assertSee('Каква информация обработваме');

        $this->withSession(['locale' => 'en'])
            ->get('/pages/terms-of-use')
            ->assertOk()
            ->assertSee('Terms of Use');
    }

    /**
     * Confirms page routes reject other content types and unpublished entries.
     */
    public function test_public_page_route_only_displays_published_page_content(): void
    {
        NewsPost::factory()->published()->create([
            'slug' => 'news-entry',
        ]);
        NewsPost::factory()->page()->create([
            'slug' => 'draft-page',
        ]);

        $this->get('/pages/news-entry')->assertNotFound();
        $this->get('/pages/draft-page')->assertNotFound();
    }

    /**
     * Confirms the footer uses the website owner fallback and exposes legal navigation.
     */
    public function test_footer_and_cookie_notice_render_public_legal_links(): void
    {
        GameServer::factory()->create([
            'name' => 'DreamMu 97d+99i Easy',
            'is_current' => true,
        ]);
        $this->seed(ContentPageSeeder::class);

        $response = $this->get('/pages/cookies');

        $response
            ->assertOk()
            ->assertSee('&copy; '.now()->year.' MuOnline Web.', false)
            ->assertSee('Website created by')
            ->assertSee('https://interweb.bg', false)
            ->assertSee('/pages/privacy-policy', false)
            ->assertSee('/pages/terms-of-use', false)
            ->assertSee('Essential cookies');
    }
}
