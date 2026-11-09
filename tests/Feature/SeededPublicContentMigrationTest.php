<?php

namespace Tests\Feature;

use App\Models\Download;
use App\Models\GameServer;
use App\Models\NewsPost;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies legacy generic demo copy follows the current server safely.
 */
final class SeededPublicContentMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms exact defaults are upgraded without replacing edited content.
     */
    public function test_updates_only_legacy_seeded_version_copy(): void
    {
        GameServer::factory()->current()->create([
            'name' => 'DreamMu Season 6',
            'version' => 'Season 6',
        ]);
        $welcome = NewsPost::factory()->create([
            'slug' => 'welcome-to-dreammu',
            'title_en' => 'Welcome to DreamMu 97d+99i',
            'title_bg' => 'Добре дошли в DreamMu 97d+99i',
            'body_en' => 'DreamMu opens the gates to a classic 97d+99i experience. Existing English body.',
            'body_bg' => 'DreamMu отваря вратите към класическо 97d+99i изживяване. Съществуващ български текст.',
        ]);
        $custom = NewsPost::factory()->create([
            'slug' => 'custom-version-article',
            'title_en' => 'Administrator 97d+99i archive',
        ]);
        $download = Download::factory()->create([
            'slug' => 'full-client-mirror-1',
            'description_en' => 'Complete DreamMu 97d+99i client hosted on the Europe mirror.',
            'description_bg' => 'Пълен DreamMu 97d+99i клиент, разположен на Европа mirror.',
            'version' => '97d+99i',
        ]);

        $migration = require database_path(
            'migrations/2026_11_09_223325_make_seeded_public_content_version_aware.php',
        );

        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();

        $this->assertSame('Welcome to DreamMu Season 6', $welcome->refresh()->title_en);
        $this->assertStringStartsWith(
            'DreamMu Season 6 opens the gates to a classic Season 6 experience.',
            $welcome->body_en,
        );
        $this->assertSame('Administrator 97d+99i archive', $custom->refresh()->title_en);
        $this->assertSame('Season 6', $download->refresh()->version);
        $this->assertStringStartsWith(
            'Complete DreamMu Season 6 client',
            $download->description_en,
        );
    }
}
