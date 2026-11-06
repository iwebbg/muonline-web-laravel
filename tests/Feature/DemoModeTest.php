<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\Administrator;
use App\Models\EventScheduleEntry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies demo installations expose reads while protecting persistent changes.
 */
final class DemoModeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables demo restrictions with one case-insensitive game account allow-list.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo.enabled', true);
        config()->set('demo.account_ids', ['test1']);
    }

    /**
     * Confirms administrators may inspect settings without changing them.
     */
    public function test_administrator_can_view_but_cannot_create_in_demo_mode(): void
    {
        $administrator = Administrator::factory()->create();

        $viewResponse = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.event-schedules.index'));
        $writeResponse = $this->withCsrf()
            ->actingAs($administrator, 'admin')
            ->post(route('admin.event-schedules.store'));

        $viewResponse->assertOk();
        $writeResponse
            ->assertForbidden()
            ->assertSee('This action is unavailable in the demo.');
        $this->assertSame(0, EventScheduleEntry::query()->count());
    }

    /**
     * Confirms demo restrictions do not trap an administrator session.
     */
    public function test_administrator_can_log_out_in_demo_mode(): void
    {
        $administrator = Administrator::factory()->create();

        $response = $this->withCsrf()
            ->actingAs($administrator, 'admin')
            ->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    /**
     * Confirms unlisted game accounts cannot execute persistent player operations.
     */
    public function test_unlisted_account_cannot_execute_player_mutation_in_demo_mode(): void
    {
        $response = $this->withCsrf()
            ->actingAs($this->account('visitor'))
            ->postJson(route('account.character-reset'));

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'The website is currently running in demo mode, so this change is not permitted.',
            );
    }

    /**
     * Confirms allow-listed account identifiers reach real player endpoints.
     */
    public function test_allow_listed_account_reaches_player_mutation_endpoint(): void
    {
        $response = $this->withCsrf()
            ->actingAs($this->account('Test1'))
            ->postJson(route('account.character-reset'));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['characterName', 'idempotencyKey']);
    }

    /**
     * Confirms previews remain available to authenticated non-demo accounts.
     */
    public function test_unlisted_account_can_request_non_persistent_preview(): void
    {
        $response = $this->withCsrf()
            ->actingAs($this->account('visitor'))
            ->postJson(route('account.reset-preview'));

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('characterName');
    }

    /**
     * Confirms identity changes remain blocked for allow-listed demo accounts.
     */
    public function test_allow_listed_account_cannot_change_credentials(): void
    {
        $response = $this->withCsrf()
            ->actingAs($this->account('test1'))
            ->putJson(route('account.credentials.update'));

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'The website is currently running in demo mode, so this change is not permitted.',
            );
    }

    /**
     * Confirms side-effecting activation links are rejected before account changes.
     */
    public function test_account_activation_link_is_blocked_in_demo_mode(): void
    {
        $response = $this->get(route('account.activation', [
            'account' => 'test1',
            'token' => str_repeat('a', 64),
        ]));

        $response
            ->assertForbidden()
            ->assertSee('This action is unavailable in the demo.');
    }

    /**
     * Confirms normal installations continue to reach the original validation boundary.
     */
    public function test_disabled_demo_mode_does_not_block_admin_mutations(): void
    {
        config()->set('demo.enabled', false);
        $administrator = Administrator::factory()->create();

        $response = $this->withCsrf()
            ->actingAs($administrator, 'admin')
            ->postJson(route('admin.event-schedules.store'));

        $response->assertUnprocessable();
    }

    /**
     * Builds one authenticated legacy account for demo access checks.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Demo Player',
            email: 'demo@example.com',
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }

    /**
     * Prepares one valid CSRF session and header for a protected request.
     */
    private function withCsrf(): self
    {
        $csrfToken = 'demo-mode-test-token';

        return $this
            ->withSession(['_token' => $csrfToken])
            ->withHeader('X-CSRF-TOKEN', $csrfToken);
    }
}
