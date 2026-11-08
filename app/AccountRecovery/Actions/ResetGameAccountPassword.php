<?php

namespace App\AccountRecovery\Actions;

use App\AccountCredentials\AccountCredentialStatus;
use App\AccountRecovery\Exceptions\PasswordRecoveryRejected;
use App\AccountRecovery\PasswordRecoveryBlockReason;
use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Game\GameAccountPasswordScheme;
use App\Models\AccountPasswordRecoveryOperation;
use App\Models\GameAccountPasswordReset;
use SensitiveParameter;
use Throwable;

/**
 * Claims a single-use token before replacing one legacy game account password.
 */
final readonly class ResetGameAccountPassword
{
    /**
     * Creates the action with its version-specific password write boundary.
     */
    public function __construct(
        private AccountPasswordRecoveryRepository $accountPasswordRecoveryRepository,
    ) {}

    /**
     * Burns a valid token and performs one audited plaintext password replacement.
     */
    public function handle(
        string $accountId,
        #[SensitiveParameter] string $rawToken,
        #[SensitiveParameter] string $newPassword,
    ): void {
        $this->ensureRecoveryWritesAreSupported();

        $operation = AccountPasswordRecoveryOperation::query()->create([
            'account_id' => $accountId,
            'operation_type' => 'password_reset',
            'status' => AccountCredentialStatus::Pending,
            'settings_snapshot' => [
                'password_scheme' => (string) config('game.account_password_scheme'),
                'token_storage' => 'sha256',
                'single_use_claim' => true,
            ],
        ]);

        try {
            $claimedTokens = GameAccountPasswordReset::query()
                ->where('account_id', $accountId)
                ->where('token_hash', hash('sha256', $rawToken))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);

            if ($claimedTokens !== 1) {
                throw new PasswordRecoveryRejected(
                    PasswordRecoveryBlockReason::InvalidToken,
                );
            }

            $this->accountPasswordRecoveryRepository->replacePassword(
                accountId: $accountId,
                newPassword: $newPassword,
            );
        } catch (PasswordRecoveryRejected $exception) {
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
            'before_snapshot' => [
                'password_present' => true,
            ],
            'after_snapshot' => [
                'password_present' => true,
                'password_changed' => true,
                'token_consumed' => true,
            ],
            'completed_at' => now(),
        ]);
    }

    /**
     * Restricts recovery writes to the verified plaintext 97d+99i adapter.
     */
    private function ensureRecoveryWritesAreSupported(): void
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new PasswordRecoveryRejected(
                PasswordRecoveryBlockReason::WritesDisabled,
                503,
            );
        }

        if (GameAccountPasswordScheme::tryFrom((string) config('game.account_password_scheme')) === null) {
            throw new PasswordRecoveryRejected(
                PasswordRecoveryBlockReason::PasswordSchemeUnsupported,
                503,
            );
        }
    }
}
