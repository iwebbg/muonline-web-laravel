<?php

namespace App\AccountRegistration\Actions;

use App\AccountRegistration\AccountRegistrationBlockReason;
use App\AccountRegistration\AccountRegistrationResult;
use App\AccountRegistration\AccountRegistrationStatus;
use App\AccountRegistration\Exceptions\AccountRegistrationRejected;
use App\Game\Contracts\AccountRegistrationRepository;
use App\Game\GameAccountPasswordScheme;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\AccountRegistrationOperation;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use App\Website\WebsiteSettingsManager;
use SensitiveParameter;
use Throwable;

/**
 * Coordinates write safety, durable audit state and one game-account registration.
 */
final readonly class RegisterGameAccount
{
    /**
     * Creates the action with its version-specific game persistence boundary.
     */
    public function __construct(
        private AccountRegistrationRepository $accountRegistrationRepository,
        private GameVersionProfile $versionProfile,
        private WebsiteSettingsManager $websiteSettingsManager,
        private ModuleManager $moduleManager,
    ) {}

    /**
     * Registers one account and records only non-sensitive audit snapshots.
     */
    public function handle(
        string $username,
        #[SensitiveParameter] string $password,
        string $email,
    ): AccountRegistrationResult {
        $this->ensureRegistrationIsSupported();

        $requiresEmailVerification = $this->websiteSettingsManager->current()->requireEmailVerification;
        $requiredTables = ['MEMB_INFO'];

        if ($this->versionProfile->capabilities()->billingRecord) {
            $requiredTables[] = 'VI_CURR_INFO';
        }

        $settingsSnapshot = [
            'game_profile' => (string) config('game.profile.version'),
            'password_scheme' => (string) config('game.account_password_scheme'),
            'required_tables' => $requiredTables,
            'require_email_verification' => $requiresEmailVerification,
        ];
        $operation = AccountRegistrationOperation::query()->create([
            'account_id' => $username,
            'status' => AccountRegistrationStatus::Pending,
            'settings_snapshot' => $settingsSnapshot,
        ]);

        try {
            $result = $this->accountRegistrationRepository->register(
                username: $username,
                password: $password,
                email: $email,
                requiresEmailVerification: $requiresEmailVerification,
            );
        } catch (AccountRegistrationRejected $exception) {
            $operation->update([
                'status' => AccountRegistrationStatus::Failed,
                'failure_code' => $exception->reason->value,
                'completed_at' => now(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $operation->update([
                'status' => AccountRegistrationStatus::Failed,
                'failure_code' => 'unexpected_error',
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $operation->update([
            'status' => AccountRegistrationStatus::Completed,
            'after_snapshot' => $result->toAuditSnapshot(),
            'completed_at' => now(),
        ]);

        return $result;
    }

    /**
     * Rejects registration unless the verified plaintext adapter may write safely.
     */
    private function ensureRegistrationIsSupported(): void
    {
        if (! $this->moduleManager->enabled(ServerModule::Registration)) {
            throw new AccountRegistrationRejected(AccountRegistrationBlockReason::RegistrationDisabled, 404);
        }

        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new AccountRegistrationRejected(
                AccountRegistrationBlockReason::WritesDisabled,
                503,
            );
        }

        if (GameAccountPasswordScheme::tryFrom((string) config('game.account_password_scheme')) === null) {
            throw new AccountRegistrationRejected(
                AccountRegistrationBlockReason::PasswordSchemeUnsupported,
                503,
            );
        }
    }
}
