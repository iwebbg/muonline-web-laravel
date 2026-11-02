<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies secure creation of permission-scoped Game Master identities.
 */
final class CreateGameMasterCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the command stores a hashed Game Master with exact permissions.
     */
    public function test_command_creates_permission_scoped_game_master(): void
    {
        $this->artisan('admin:create-game-master', [
            '--permission' => [
                'moderate_accounts',
                'view_audit_log',
            ],
        ])
            ->expectsQuestion('Game Master name', 'Senior GM')
            ->expectsQuestion('Game Master email', 'gm@example.com')
            ->expectsQuestion('Password', 'VeryStrong!Password123')
            ->expectsQuestion('Confirm password', 'VeryStrong!Password123')
            ->expectsOutput('Game Master created successfully.')
            ->assertSuccessful();

        $gameMaster = Administrator::query()
            ->where('email', 'gm@example.com')
            ->firstOrFail();

        $this->assertSame(AdministratorRole::GameMaster, $gameMaster->role);
        $this->assertSame([
            'moderate_accounts',
            'view_audit_log',
        ], $gameMaster->permissions);
        $this->assertTrue(Hash::check('VeryStrong!Password123', $gameMaster->password));
        $this->assertNotSame('VeryStrong!Password123', $gameMaster->password);
    }

    /**
     * Confirms a Game Master cannot be created without a capability.
     */
    public function test_command_rejects_empty_permission_set(): void
    {
        $this->artisan('admin:create-game-master')
            ->expectsQuestion('Game Master name', 'Senior GM')
            ->expectsQuestion('Game Master email', 'gm@example.com')
            ->expectsQuestion('Password', 'VeryStrong!Password123')
            ->expectsQuestion('Confirm password', 'VeryStrong!Password123')
            ->assertFailed();

        $this->assertDatabaseMissing('administrators', [
            'email' => 'gm@example.com',
        ]);
    }

    /**
     * Confirms arbitrary permission strings cannot cross the allow-list boundary.
     */
    public function test_command_rejects_unknown_permission(): void
    {
        $this->artisan('admin:create-game-master', [
            '--permission' => ['database_superuser'],
        ])
            ->expectsQuestion('Game Master name', 'Senior GM')
            ->expectsQuestion('Game Master email', 'gm@example.com')
            ->expectsQuestion('Password', 'VeryStrong!Password123')
            ->expectsQuestion('Confirm password', 'VeryStrong!Password123')
            ->assertFailed();

        $this->assertDatabaseMissing('administrators', [
            'email' => 'gm@example.com',
        ]);
    }
}
