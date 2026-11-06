<?php

namespace Tests\Feature;

use App\Content\NewsPostStatus;
use App\Models\NewsPost;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies public publication rules, localization fallback and output escaping.
 */
final class NewsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the home page displays only the three newest public articles.
     */
    public function test_home_displays_three_latest_published_news_articles(): void
    {
        NewsPost::factory()->published()->create([
            'slug' => 'oldest-public-news',
            'title_en' => 'Oldest public news',
            'published_at' => now()->subDays(4),
        ]);
        NewsPost::factory()->published()->create([
            'slug' => 'third-latest-news',
            'title_en' => 'Third latest news',
            'published_at' => now()->subDays(3),
        ]);
        NewsPost::factory()->published()->create([
            'slug' => 'second-latest-news',
            'title_en' => 'Second latest news',
            'published_at' => now()->subDays(2),
        ]);
        NewsPost::factory()->published()->create([
            'slug' => 'latest-news',
            'title_en' => 'Latest public news',
            'published_at' => now()->subDay(),
        ]);
        NewsPost::factory()->create([
            'slug' => 'private-home-news',
            'title_en' => 'Private home news',
        ]);
        NewsPost::factory()->create([
            'slug' => 'future-home-news',
            'title_en' => 'Future home news',
            'status' => NewsPostStatus::Published,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Latest news')
            ->assertSeeInOrder([
                'Latest public news',
                'Second latest news',
                'Third latest news',
            ])
            ->assertDontSee('Oldest public news')
            ->assertDontSee('Private home news')
            ->assertDontSee('Future home news');
    }

    /**
     * Confirms only currently published articles appear in public routes.
     */
    public function test_public_news_hides_drafts_and_future_articles(): void
    {
        NewsPost::factory()->create([
            'slug' => 'private-draft',
            'title_en' => 'Private draft',
        ]);
        NewsPost::factory()->create([
            'slug' => 'future-news',
            'title_en' => 'Future news',
            'status' => NewsPostStatus::Published,
            'published_at' => now()->addHour(),
        ]);
        NewsPost::factory()->published()->create([
            'slug' => 'realm-opening',
            'title_en' => 'Realm opening',
        ]);

        $indexResponse = $this->get('/news');

        $indexResponse
            ->assertOk()
            ->assertSee('Realm opening')
            ->assertDontSee('Private draft')
            ->assertDontSee('Future news');
        $this->get('/news/private-draft')->assertNotFound();
        $this->get('/news/future-news')->assertNotFound();
        $this->get('/news/realm-opening')->assertOk()->assertSee('Realm opening');
    }

    /**
     * Confirms Bulgarian content falls back field-by-field to English.
     */
    public function test_bulgarian_news_uses_english_fallback_for_missing_fields(): void
    {
        NewsPost::factory()->published()->create([
            'slug' => 'bilingual-update',
            'title_en' => 'English fallback title',
            'title_bg' => 'Българско заглавие',
            'excerpt_en' => 'English fallback excerpt.',
            'excerpt_bg' => null,
            'body_en' => 'English fallback body.',
            'body_bg' => null,
        ]);

        $response = $this
            ->withSession(['locale' => 'bg'])
            ->get('/news/bilingual-update');

        $response
            ->assertOk()
            ->assertSee('Българско заглавие')
            ->assertSee('English fallback excerpt.')
            ->assertSee('English fallback body.');
    }

    /**
     * Confirms stored article text is escaped before preserving line breaks.
     */
    public function test_article_body_does_not_render_stored_script_markup(): void
    {
        NewsPost::factory()->published()->create([
            'slug' => 'safe-output',
            'title_en' => 'Safe output',
            'body_en' => '<script>alert("news-xss")</script>',
        ]);

        $response = $this->get('/news/safe-output');

        $response
            ->assertOk()
            ->assertDontSee('<script>alert("news-xss")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;news-xss&quot;)&lt;/script&gt;', false);
    }
}
