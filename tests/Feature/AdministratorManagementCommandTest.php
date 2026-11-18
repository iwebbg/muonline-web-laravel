<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\AdministratorIdentityRevision;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Exercises the desktop manager CLI contract against disposable application identities.
 */
final class AdministratorManagementCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Lists stable identity metadata without credential material, including Game Masters.
     */
    public function test_list_returns_only_safe_identity_fields(): void
    {
        $owner = Administrator::factory()->create(['name' => '<info>Owner</info>']);
        Administrator::factory()->gameMaster()->create();

        [$status, $result] = $this->runJson(['command' => 'admin:list', '--json' => true]);

        $this->assertSame(0, $status);
        $this->assertCount(2, $result['administrators']);
        $this->assertSame(['id', 'name', 'email', 'role', 'is_active'], array_keys($result['administrators'][0]));
        $this->assertSame($owner->id, $result['administrators'][0]['id']);
        $this->assertSame('<info>Owner</info>', $result['administrators'][0]['name']);
        $this->assertSame('game_master', $result['administrators'][1]['role']);
    }

    /**
     * Returns a valid empty list for a newly installed application.
     */
    public function test_list_accepts_an_empty_installation(): void
    {
        [$status, $result] = $this->runJson(['command' => 'admin:list', '--json' => true]);

        $this->assertSame(0, $status);
        $this->assertSame(['administrators' => []], $result);
    }

    /**
     * Updates identity details and activity with a password-free audit entry.
     */
    public function test_update_changes_details_and_can_deactivate_and_reactivate(): void
    {
        Administrator::factory()->create();
        $identity = Administrator::factory()->create(['remember_token' => 'old-token']);

        [$status, $result] = $this->runJson(['command' => 'admin:update', 'id' => $identity->id, '--name' => 'Нов администратор', '--email' => 'new@example.com', '--active' => '0', '--json' => true]);

        $this->assertSame(0, $status);
        $this->assertFalse($result['administrator']['is_active']);
        $this->assertSame('Нов администратор', $identity->fresh()->name);
        $this->assertSame('new@example.com', $identity->fresh()->email);
        $this->assertNotSame('old-token', $identity->fresh()->remember_token);
        $revision = AdministratorIdentityRevision::query()->sole();
        $this->assertSame('cli_updated', $revision->action);
        $this->assertArrayNotHasKey('password', $revision->after_snapshot);

        $this->artisan('admin:update', ['id' => $identity->id, '--active' => '1'])->assertSuccessful();
        $this->assertTrue($identity->fresh()->is_active);
    }

    /**
     * Reads a new password from stdin without exposing it in JSON or audit snapshots.
     */
    public function test_password_rotation_uses_private_stdin(): void
    {
        $identity = Administrator::factory()->create();

        [$status, $result] = $this->runJson(['command' => 'admin:update', 'id' => $identity->id, '--password-stdin' => true, '--json' => true], "NewStrong!Password123\n");

        $this->assertSame(0, $status);
        $this->assertTrue(Hash::check('NewStrong!Password123', $identity->fresh()->password));
        $this->assertStringNotContainsString('NewStrong!Password123', json_encode($result));
        $revision = AdministratorIdentityRevision::query()->sole();
        $this->assertSame('cli_password_rotated', $revision->action);
        $this->assertArrayNotHasKey('password', $revision->before_snapshot);
        $this->assertArrayNotHasKey('password', $revision->after_snapshot);
    }

    /**
     * Rejects unsafe changes atomically and emits parseable error JSON.
     *
     * @param  array<string, mixed>  $options
     */
    #[DataProvider('invalidChanges')]
    public function test_invalid_changes_leave_identity_and_audit_unchanged(array $options, string $stdin, string $field): void
    {
        $identity = Administrator::factory()->create();
        $original = $identity->fresh()->getAttributes();

        [$status, $result] = $this->runJson(['command' => 'admin:update', 'id' => $identity->id, '--json' => true, ...$options], $stdin);

        $this->assertSame(1, $status);
        $this->assertArrayHasKey($field, $result['errors']);
        $this->assertSame($original, $identity->fresh()->getAttributes());
        $this->assertDatabaseCount('administrator_identity_revisions', 0);
    }

    /**
     * Supplies separate invalid input branches without placing real credentials in arguments.
     *
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidChanges(): array
    {
        return [
            'no change' => [[], '', 'administrator'],
            'empty name' => [['--name' => ''], '', 'name'],
            'unsupported name' => [['--name' => 'Owner😀'], '', 'name'],
            'invalid email' => [['--email' => 'invalid'], '', 'email'],
            'invalid activity' => [['--active' => 'yes'], '', 'is_active'],
            'weak password' => [['--password-stdin' => true], "weak\n", 'password'],
            'bcrypt byte overflow' => [['--password-stdin' => true], str_repeat('Aa1!', 19)."\n", 'password'],
            'multibyte bcrypt overflow' => [['--password-stdin' => true], str_repeat('я', 35)."Aa1!\n", 'password'],
            'null byte password' => [['--password-stdin' => true], "Strong!Password123\0tail\n", 'password'],
            'missing stdin' => [['--password-stdin' => true], '', 'password'],
            'conflicting password modes' => [['--password' => true, '--password-stdin' => true], '', 'password'],
            'noninteractive prompt' => [['--password' => true], '', 'password'],
        ];
    }

    /**
     * Rejects duplicate email addresses without partially changing the name.
     */
    public function test_duplicate_email_is_rejected(): void
    {
        $other = Administrator::factory()->create();
        $identity = Administrator::factory()->create();

        [$status, $result] = $this->runJson(['command' => 'admin:update', 'id' => $identity->id, '--email' => $other->email, '--name' => 'Changed', '--json' => true]);

        $this->assertSame(1, $status);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertSame($identity->name, $identity->fresh()->name);
    }

    /**
     * Prevents removal of the last active full administrator even when a GM exists.
     */
    public function test_last_active_administrator_is_protected(): void
    {
        $identity = Administrator::factory()->create();
        Administrator::factory()->gameMaster()->create();
        Administrator::factory()->create(['is_active' => false]);

        [$deleteStatus, $deleteResult] = $this->runJson(['command' => 'admin:delete', 'id' => $identity->id, '--force' => true, '--json' => true]);
        [$updateStatus, $updateResult] = $this->runJson(['command' => 'admin:update', 'id' => $identity->id, '--active' => '0', '--json' => true]);

        $this->assertSame(1, $deleteStatus);
        $this->assertSame(1, $updateStatus);
        $this->assertSame('The last active administrator cannot be deleted or deactivated.', $deleteResult['errors']['administrator'][0]);
        $this->assertSame($deleteResult, $updateResult);
        $this->assertTrue($identity->fresh()->is_active);
        $this->assertDatabaseCount('administrator_identity_revisions', 0);
    }

    /**
     * Removes an explicitly confirmed unreferenced identity and retains its audit snapshot.
     */
    public function test_delete_removes_an_identity_and_records_its_snapshot(): void
    {
        Administrator::factory()->create();
        $identity = Administrator::factory()->create();

        [$status, $result] = $this->runJson(['command' => 'admin:delete', 'id' => $identity->id, '--force' => true, '--json' => true]);

        $this->assertSame(0, $status);
        $this->assertSame(['id' => $identity->id, 'deleted' => true], $result['administrator']);
        $this->assertModelMissing($identity);
        $revision = AdministratorIdentityRevision::query()->sole();
        $this->assertNull($revision->target_administrator_id);
        $this->assertSame($identity->email, $revision->before_snapshot['email']);
        $this->assertSame('cli_deleted', $revision->action);
    }

    /**
     * Requires explicit confirmation when the manager invokes deletion without a terminal.
     */
    public function test_noninteractive_delete_requires_force(): void
    {
        $identity = Administrator::factory()->create();

        [$status, $result] = $this->runJson(['command' => 'admin:delete', 'id' => $identity->id, '--json' => true]);

        $this->assertSame(1, $status);
        $this->assertArrayHasKey('confirmation', $result['errors']);
        $this->assertModelExists($identity);
    }

    /**
     * Returns sanitized errors for nonexistent IDs without recording a mutation.
     */
    public function test_unknown_identity_is_rejected(): void
    {
        [$status, $result] = $this->runJson(['command' => 'admin:update', 'id' => 999, '--name' => 'Missing', '--json' => true]);

        $this->assertSame(1, $status);
        $this->assertSame(['administrator' => ['Administrator not found.']], $result['errors']);
    }

    /**
     * Preserves identities referenced by audit history and explains deactivation instead.
     */
    public function test_delete_preserves_referenced_history(): void
    {
        Administrator::factory()->create();
        $identity = Administrator::factory()->create();
        $this->artisan('admin:update', ['id' => $identity->id, '--name' => 'Audited'])->assertSuccessful();

        [$status, $result] = $this->runJson(['command' => 'admin:delete', 'id' => $identity->id, '--force' => true, '--json' => true]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('--active=0', $result['errors']['database'][0]);
        $this->assertModelExists($identity);
        $this->assertDatabaseCount('administrator_identity_revisions', 1);
    }

    /**
     * Runs the real console kernel with a private stdin stream and parses only JSON output.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{int, array<string, mixed>}
     */
    private function runJson(array $arguments, string $stdin = ''): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $stdin);
        rewind($stream);
        $input = new ArrayInput($arguments);
        $input->setInteractive(false);
        $input->setStream($stream);
        $output = new BufferedOutput;

        try {
            $status = $this->app->make(Kernel::class)->handle($input, $output);

            return [$status, json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR)];
        } finally {
            fclose($stream);
        }
    }
}
