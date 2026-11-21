<?php

namespace Tests\Feature;

use App\Models\Administrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies authenticated, localized support links without exposing runtime secrets.
 */
final class AdminSupportTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Requires an application administrator session before showing support content.
     */
    public function test_guest_is_redirected_to_administrator_login(): void
    {
        $this->get('/admin/support')->assertRedirect(route('admin.login'));
    }

    /**
     * Shows translated support channels and all existing donation destinations.
     */
    #[DataProvider('locales')]
    public function test_administrator_sees_localized_support_channels(string $locale, string $heading): void
    {
        $administrator = Administrator::factory()->create();
        config()->set('database.connections.game.password', 'private-support-test-secret');

        $this->actingAs($administrator, 'admin')->withSession(['locale' => $locale])
            ->get('/admin/support')
            ->assertOk()
            ->assertSee($heading)
            ->assertSee('https://github.com/iwebbg/muonline-web-laravel/issues/new', false)
            ->assertSee('https://github.com/iwebbg/muonline-web-laravel', false)
            ->assertSee('mailto:office@interweb.bg', false)
            ->assertSee('https://buymeacoffee.com/interweb.bg', false)
            ->assertSee('https://ko-fi.com/interwebltd', false)
            ->assertSee('https://paypal.me/interwebltd', false)
            ->assertDontSee('ui.support.')
            ->assertDontSee('private-support-test-secret');
    }

    /**
     * Keeps documentation and support available to a GM without management permissions.
     */
    public function test_game_master_can_access_support_in_demo_mode(): void
    {
        config()->set('demo.enabled', true);

        $this->actingAs(Administrator::factory()->gameMaster()->create(), 'admin')
            ->get('/admin/support')
            ->assertOk()
            ->assertSee('href="'.route('admin.support').'"', false);
    }

    /**
     * Covers both supported interface languages.
     *
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        return [
            'English' => ['en', 'Help & Support'],
            'Bulgarian' => ['bg', 'Помощ и поддръжка'],
        ];
    }
}
