<?php

namespace Tests\Feature;

use App\Game\Contracts\GameServerStatus;
use App\Game\Contracts\RealmClock;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * Verifies that the first public, account and administration shells render.
 */
class InteractiveShellTest extends TestCase
{
    /**
     * Confirms that the public server page exposes its interactive content.
     */
    public function test_public_server_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Your adventure');
        $response->assertSee('Fixture data');
        $response->assertSee('Blood Castle');
        $response->assertSee('eventSchedule(', false);
        $response->assertSee('Server event schedule');
        $response->assertSee('View 5 more');
        $response->assertSee('Sky Event');
        $response->assertDontSee('12:48');
        $response->assertSee('Top players');
        $response->assertDontSee(route('admin.dashboard'), false);
    }

    /**
     * Confirms an unavailable game endpoint is displayed as offline everywhere.
     */
    public function test_public_server_page_displays_offline_status(): void
    {
        $serverStatus = $this->mock(GameServerStatus::class);

        $serverStatus
            ->shouldReceive('isOnline')
            ->atLeast()
            ->once()
            ->andReturnFalse();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Server offline');
        $response->assertSee('OFFLINE');
        $response->assertSee('<title>MuOnline 97d+99i · Server offline</title>', false);
        $response->assertSee('status-dot-server-offline', false);
        $response->assertSee('online-badge-offline', false);
    }

    /**
     * Confirms that public event occurrences are calculated from the realm clock.
     */
    public function test_public_event_schedule_uses_authoritative_realm_clock(): void
    {
        $realmClock = $this->mock(RealmClock::class);

        $realmClock
            ->shouldReceive('now')
            ->once()
            ->andReturn(new DateTimeImmutable('2026-11-02T10:20:00+02:00'));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('2026-11-02T11:00:00+02:00');
        $response->assertSee('2026-11-02T10:30:00+02:00');
    }

    /**
     * Confirms that administration requires its separate authentication boundary.
     */
    public function test_administration_dashboard_requires_administrator_identity(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    /**
     * Confirms that a visitor can switch to the complete Bulgarian interface.
     */
    public function test_visitor_can_switch_to_bulgarian(): void
    {
        $csrfToken = 'localization-test-token';

        $response = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->from('/login')
            ->post('/locale/bg', [
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('locale', 'bg');

        $localizedResponse = $this->get('/login');

        $localizedResponse->assertOk();
        $localizedResponse->assertSee('lang="bg"', false);
        $localizedResponse->assertSee('Влез в акаунта си');

        $homeResponse = $this->get('/');

        $homeResponse->assertOk();
        $homeResponse->assertSee('Светът');
        $homeResponse->assertSee('Примерни данни');
        $homeResponse->assertSee('Топ играчи');

        $adminResponse = $this->get('/admin/login');

        $adminResponse->assertOk();
        $adminResponse->assertSee('Потвърди администраторската идентичност');
    }

    /**
     * Confirms that missing Bulgarian strings fall back to English.
     */
    public function test_missing_translation_falls_back_to_english(): void
    {
        $this->app->setLocale('bg');

        $translation = __('ui.fallback_probe');

        $this->assertSame('English fallback active', $translation);
    }

    /**
     * Confirms that unsupported locale values are not accepted.
     */
    public function test_unsupported_locale_is_rejected(): void
    {
        $response = $this->post('/locale/de');

        $response->assertNotFound();
        $response->assertSessionMissing('locale');
    }
}
