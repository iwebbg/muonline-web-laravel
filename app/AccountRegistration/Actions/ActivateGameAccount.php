<?php

namespace App\AccountRegistration\Actions;

use App\Game\Contracts\AccountActivationRepository;
use App\Models\GameAccountActivation;
use Illuminate\Database\DatabaseManager;

/**
 * Consumes one activation token and unblocks its exact legacy account.
 */
final readonly class ActivateGameAccount
{
    /**
     * Creates the action with application and game persistence boundaries.
     */
    public function __construct(
        private DatabaseManager $database,
        private AccountActivationRepository $accountActivationRepository,
    ) {}

    /**
     * Activates the account once while preventing token replay under a row lock.
     */
    public function handle(string $accountId, string $rawToken): bool
    {
        return $this->database
            ->connection()
            ->transaction(function () use ($accountId, $rawToken): bool {
                $activation = GameAccountActivation::query()
                    ->where('account_id', $accountId)
                    ->where('token_hash', hash('sha256', $rawToken))
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->first();

                if (! $activation instanceof GameAccountActivation) {
                    return false;
                }

                if (! $this->accountActivationRepository->activate($accountId)) {
                    return false;
                }

                $activation->update(['used_at' => now()]);

                return true;
            }, 3);
    }
}
