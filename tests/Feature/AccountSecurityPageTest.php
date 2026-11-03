<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the dedicated account security page and its private data boundary.
 */
final class AccountSecurityPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms guests cannot inspect private account security settings.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('account.security.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Confirms the page separates email and password controls without exposing the password.
     */
    public function test_authenticated_account_can_open_security_page(): void
    {
        $response = $this
            ->actingAs($this->account('owner'))
            ->get(route('account.security.index'));

        $response
            ->assertOk()
            ->assertSee('Change email address')
            ->assertSee('Change game password')
            ->assertSee('owner@example.com')
            ->assertSee(route('account.credentials.update'), false)
            ->assertDontSee('secret1');
    }

    /**
     * Builds one authenticated legacy account identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Security Player',
            email: 'owner@example.com',
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
