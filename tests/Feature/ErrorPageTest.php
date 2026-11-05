<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that browser failures retain the product visual language and locale.
 */
final class ErrorPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms an unknown English route renders the custom 404 experience.
     */
    public function test_unknown_route_returns_custom_404_page(): void
    {
        $response = $this->get('/this-realm-path-does-not-exist');

        $response
            ->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('This path has vanished from the realm.')
            ->assertSee('Return home')
            ->assertSee('MUONLINE')
            ->assertDontSee('404 | Not Found');
    }

    /**
     * Confirms error pages render the active managed website identity.
     */
    public function test_unknown_route_uses_managed_header_title_logo_and_footer_identity(): void
    {
        WebsiteSetting::query()->create([
            'brand_title' => 'ERRORMU',
            'brand_subtitle' => 'Season 3',
            'logo_path' => 'website-branding/error-logo.png',
            'page_title_suffix' => 'ErrorMU Realm',
            'copyright_holder' => 'Realm Company Ltd.',
            'revision' => 1,
        ]);

        $response = $this->get('/missing-managed-identity-page');

        $response
            ->assertNotFound()
            ->assertSee('<title>Page not found - ErrorMU Realm</title>', false)
            ->assertSee('/storage/website-branding/error-logo.png', false)
            ->assertSee('ERRORMU')
            ->assertSee('Season 3')
            ->assertSee('&copy; '.now()->year.' Realm Company Ltd.', false)
            ->assertDontSee('MUONLINE');
    }

    /**
     * Confirms the custom 404 page follows the visitor's Bulgarian locale.
     */
    public function test_unknown_route_returns_localized_bulgarian_404_page(): void
    {
        $response = $this
            ->withSession(['locale' => 'bg'])
            ->get('/липсваща-страница');

        $response
            ->assertNotFound()
            ->assertSee('lang="bg"', false)
            ->assertSee('Този път е изчезнал от света.')
            ->assertSee('Към началото');
    }

    /**
     * Confirms authorization failures use the same custom status layout.
     */
    public function test_forbidden_admin_request_returns_custom_403_page(): void
    {
        $gameMaster = Administrator::factory()->gameMaster()->create();

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->get(route('admin.dashboard'));

        $response
            ->assertForbidden()
            ->assertSee('This area is beyond your permissions.')
            ->assertSee('Return home');
    }
}
