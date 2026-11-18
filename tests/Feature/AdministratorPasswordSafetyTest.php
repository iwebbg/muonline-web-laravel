<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies every administrator credential entry point rejects bcrypt-truncated passwords.
 */
final class AdministratorPasswordSafetyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Rejects overlong interactive passwords without creating an owner or Game Master.
     */
    #[DataProvider('creationCommands')]
    public function test_creation_commands_reject_overlong_passwords(string $command, string $label): void
    {
        $password = str_repeat('Aa1!', 19);

        $this->artisan($command)
            ->expectsQuestion($label.' name', 'Owner')
            ->expectsQuestion($label.' email', 'audit@example.com')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('The password must not exceed 72 bytes when using bcrypt.')
            ->assertFailed();

        $this->assertDatabaseCount('administrators', 0);
    }

    /**
     * Names the two interactive credential creation entry points.
     *
     * @return array<string, array{string, string}>
     */
    public static function creationCommands(): array
    {
        return [
            'owner' => ['admin:create', 'Administrator'],
            'game master' => ['admin:create-game-master', 'Game Master'],
        ];
    }

    /**
     * Accepts a strong password exactly at bcrypt's byte boundary.
     */
    public function test_exactly_72_byte_password_is_accepted(): void
    {
        $password = str_repeat('Aa1!', 18);

        $this->artisan('admin:create')
            ->expectsQuestion('Administrator name', 'Owner')
            ->expectsQuestion('Administrator email', 'boundary@example.com')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();

        $this->assertTrue(Hash::check($password, Administrator::query()->sole()->password));
    }

    /**
     * Rejects a legacy bcrypt suffix collision instead of authenticating a different password.
     */
    public function test_login_rejects_a_truncated_legacy_password(): void
    {
        $prefix = str_repeat('Aa1!', 18);
        $administrator = Administrator::factory()->create(['password' => $prefix.'first']);

        $this->withSession(['_token' => 'password-audit'])->post('/admin/login', [
            '_token' => 'password-audit', 'email' => $administrator->email, 'password' => $prefix.'different',
        ])->assertSessionHasErrors('password');

        $this->assertGuest('admin');
    }

    /**
     * Rejects an overlong password in the GM creation form before any identity or audit write.
     */
    public function test_game_master_creation_rejects_unsafe_password(): void
    {
        $administrator = Administrator::factory()->create();
        $password = str_repeat('я', 35).'Aa1!';

        $this->actingAs($administrator, 'admin')->withSession(['_token' => 'password-audit'])
            ->post(route('admin.game-masters.store'), [
                '_token' => 'password-audit', 'name' => 'Audit GM', 'email' => 'gm-audit@example.com',
                'password' => $password, 'password_confirmation' => $password,
                'permissions' => [AdministratorPermission::ViewAuditLog->value],
            ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('administrators', 1);
        $this->assertDatabaseCount('administrator_identity_revisions', 0);
    }

    /**
     * Rejects an unsafe password rotation while retaining the original GM hash.
     */
    public function test_game_master_update_rejects_unsafe_password(): void
    {
        $administrator = Administrator::factory()->create();
        $gameMaster = Administrator::factory()->gameMaster()->create();
        $password = str_repeat('Aa1!', 19);
        $hash = $gameMaster->password;

        $this->actingAs($administrator, 'admin')->withSession(['_token' => 'password-audit'])
            ->put(route('admin.game-masters.update', $gameMaster), [
                '_token' => 'password-audit', 'name' => $gameMaster->name, 'email' => $gameMaster->email,
                'password' => $password, 'password_confirmation' => $password, 'is_active' => true,
                'permissions' => [AdministratorPermission::ViewAuditLog->value],
            ])->assertSessionHasErrors('password');

        $this->assertSame($hash, $gameMaster->fresh()->password);
        $this->assertDatabaseCount('administrator_identity_revisions', 0);
    }
}
