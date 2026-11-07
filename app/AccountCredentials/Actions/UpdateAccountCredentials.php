<?php

namespace App\AccountCredentials\Actions;

use App\AccountCredentials\AccountCredentialBlockReason;
use App\AccountCredentials\AccountCredentialResult;
use App\AccountCredentials\AccountCredentialStatus;
use App\AccountCredentials\Exceptions\AccountCredentialUpdateRejected;
use App\Game\Contracts\AccountCredentialRepository;
use App\Game\GameAccountPasswordScheme;
use App\Models\AccountCredentialOperation;
use SensitiveParameter;
use Throwable;

/**
 * Coordinates write safety, audit state and an authenticated credential update.
 */
final readonly class UpdateAccountCredentials
{
    /**
     * Creates the action with its version-specific persistence boundary.
     */
    public function __construct(
        private AccountCredentialRepository $accountCredentialRepository,
    ) {}

    /**
     * Updates one account while excluding all credential values from web audit data.
     */
    public function handle(
        string $accountId,
        #[SensitiveParameter] string $currentPassword,
        string $email,
        #[SensitiveParameter] ?string $newPassword,
    ): AccountCredentialResult {
        $this->ensureCredentialUpdatesAreSupported();

        $settingsSnapshot = [
            'game_profile' => (string) config('game.profile.version'),
            'password_scheme' => (string) config('game.account_password_scheme'),
            'table' => 'MEMB_INFO',
        ];
        $operation = AccountCredentialOperation::query()->create([
            'account_id' => $accountId,
            'status' => AccountCredentialStatus::Pending,
            'settings_snapshot' => $settingsSnapshot,
        ]);

        try {
            $result = $this->accountCredentialRepository->update(
                accountId: $accountId,
                currentPassword: $currentPassword,
                email: $email,
                newPassword: $newPassword,
            );
        } catch (AccountCredentialUpdateRejected $exception) {
            $operation->update([
                'status' => AccountCredentialStatus::Failed,
                'failure_code' => $exception->reason->value,
                'completed_at' => now(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $operation->update([
                'status' => AccountCredentialStatus::Failed,
                'failure_code' => 'unexpected_error',
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $operation->update([
            'status' => AccountCredentialStatus::Completed,
            'before_snapshot' => $result->beforeAuditSnapshot(),
            'after_snapshot' => $result->afterAuditSnapshot(),
            'completed_at' => now(),
        ]);

        return $result;
    }

    /**
     * Rejects updates unless the verified plaintext adapter may write safely.
     */
    private function ensureCredentialUpdatesAreSupported(): void
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new AccountCredentialUpdateRejected(
                AccountCredentialBlockReason::WritesDisabled,
                503,
            );
        }

        if (GameAccountPasswordScheme::tryFrom((string) config('game.account_password_scheme')) === null) {
            throw new AccountCredentialUpdateRejected(
                AccountCredentialBlockReason::PasswordSchemeUnsupported,
                503,
            );
        }
    }
}
