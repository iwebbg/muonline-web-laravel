<?php

namespace Tests\Feature;

use App\Models\Administrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies safe interactive creation of strongly hashed administrator identities.
 */
final class CreateAdministratorCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms that the command stores a valid administrator without plaintext credentials.
     */
    public function test_command_creates_strongly_hashed_administrator(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Administrator name', 'Server Owner')
            ->expectsQuestion('Administrator email', 'owner@example.com')
            ->expectsQuestion('Password', 'VeryStrong!Password123')
            ->expectsQuestion('Confirm password', 'VeryStrong!Password123')
            ->expectsOutput('Administrator created successfully.')
            ->assertSuccessful();

        $administrator = Administrator::query()
            ->where('email', 'owner@example.com')
            ->firstOrFail();

        $this->assertSame('Server Owner', $administrator->name);
        $this->assertTrue($administrator->is_active);
        $this->assertTrue(Hash::check('VeryStrong!Password123', $administrator->password));
        $this->assertNotSame('VeryStrong!Password123', $administrator->password);
    }

    /**
     * Confirms that a weak administrator password is rejected without a database write.
     */
    public function test_command_rejects_weak_administrator_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Administrator name', 'Server Owner')
            ->expectsQuestion('Administrator email', 'owner@example.com')
            ->expectsQuestion('Password', 'weak')
            ->expectsQuestion('Confirm password', 'weak')
            ->assertFailed();

        $this->assertDatabaseMissing('administrators', [
            'email' => 'owner@example.com',
        ]);
    }
}
