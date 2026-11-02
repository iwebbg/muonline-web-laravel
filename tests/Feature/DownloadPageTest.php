<?php

namespace Tests\Feature;

use App\Content\DownloadCategory;
use App\Content\NewsPostStatus;
use App\Models\Download;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies public download publication, localization and safe rendering.
 */
final class DownloadPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only current publications appear in configured display order.
     */
    public function test_public_catalog_hides_unpublished_downloads_and_honors_order(): void
    {
        Download::factory()->create([
            'title_en' => 'Private client',
        ]);
        Download::factory()->create([
            'title_en' => 'Future patch',
            'status' => NewsPostStatus::Published,
            'published_at' => now()->addHour(),
        ]);
        Download::factory()->published()->create([
            'title_en' => 'Secondary patch',
            'sort_order' => 20,
        ]);
        Download::factory()->published()->create([
            'title_en' => 'Primary client',
            'category' => DownloadCategory::Client,
            'sort_order' => 10,
        ]);

        $response = $this->get('/downloads');

        $response
            ->assertOk()
            ->assertSee('Primary client')
            ->assertSee('Secondary patch')
            ->assertDontSee('Private client')
            ->assertDontSee('Future patch')
            ->assertSeeInOrder(['Primary client', 'Secondary patch']);
    }

    /**
     * Confirms Bulgarian fields fall back to escaped English content.
     */
    public function test_bulgarian_catalog_uses_safe_english_fallback(): void
    {
        Download::factory()->published()->create([
            'title_en' => 'English client',
            'title_bg' => 'Български клиент',
            'description_en' => '<script>alert("download-xss")</script>',
            'description_bg' => null,
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        $response = $this
            ->withSession(['locale' => 'bg'])
            ->get('/downloads');

        $response
            ->assertOk()
            ->assertSee('Български клиент')
            ->assertDontSee('<script>alert("download-xss")</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee(str_repeat('a', 64));
    }
}
