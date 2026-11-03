<?php

namespace Tests\Feature;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\Download;
use App\Models\NewsPost;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the editable demonstration content installed on a fresh website.
 */
final class DemoContentSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms every public content collection receives visible bilingual entries.
     */
    public function test_demo_content_populates_news_guides_and_three_download_mirrors(): void
    {
        $this->seed(DemoContentSeeder::class);

        $this->assertSame(3, NewsPost::query()->where('type', NewsPostType::News)->count());
        $this->assertSame(3, NewsPost::query()->where('type', NewsPostType::Guide)->count());
        $this->assertSame(3, Download::query()->count());

        $this->get('/news')
            ->assertOk()
            ->assertSee('Welcome to DreamMu 97d+99i');
        $this->get('/guides')
            ->assertOk()
            ->assertSee('Getting started on DreamMu');
        $this->get('/downloads')
            ->assertOk()
            ->assertSee('Full Client — Mirror 1')
            ->assertSee('Full Client — Mirror 2')
            ->assertSee('Full Client — Mirror 3');
    }

    /**
     * Confirms rerunning the seeder never republishes administrator-hidden content.
     */
    public function test_demo_seeder_is_idempotent_and_preserves_hidden_content(): void
    {
        $this->seed(DemoContentSeeder::class);

        $post = NewsPost::query()
            ->where('slug', 'welcome-to-dreammu')
            ->sole();
        $post->update([
            'status' => NewsPostStatus::Draft,
            'published_at' => null,
        ]);

        $this->seed(DemoContentSeeder::class);

        $this->assertSame(6, NewsPost::query()->count());
        $this->assertSame(3, Download::query()->count());
        $this->assertSame(NewsPostStatus::Draft, $post->refresh()->status);

        $this->get('/news')->assertDontSee('Welcome to DreamMu 97d+99i');
    }
}
