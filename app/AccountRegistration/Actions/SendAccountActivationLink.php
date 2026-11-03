<?php

namespace App\AccountRegistration\Actions;

use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Mail\GameAccountActivationMail;
use App\Models\GameAccountActivation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues a hashed activation token and emails its single-use link.
 */
final readonly class SendAccountActivationLink
{
    /**
     * Creates the action with the version-specific account and email lookup.
     */
    public function __construct(
        private AccountPasswordRecoveryRepository $accountPasswordRecoveryRepository,
    ) {}

    /**
     * Sends an activation link only when the exact blocked account recipient exists.
     */
    public function handle(string $accountId, string $email): bool
    {
        $recipient = $this->accountPasswordRecoveryRepository->findRecipient(
            accountId: $accountId,
            email: $email,
        );

        if ($recipient === null) {
            return false;
        }

        $expiresInMinutes = max(5, min(
            (int) config('game.account_activation_ttl_minutes', 1_440),
            10_080,
        ));
        $rawToken = Str::random(64);
        $activation = GameAccountActivation::query()->create([
            'account_id' => $recipient->accountId,
            'token_hash' => hash('sha256', $rawToken),
            'email_hash' => hash('sha256', Str::lower($recipient->email)),
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);

        GameAccountActivation::query()
            ->where('account_id', $recipient->accountId)
            ->whereNull('used_at')
            ->where('id', '!=', $activation->getKey())
            ->update(['used_at' => now()]);

        try {
            Mail::to($recipient->email)
                ->locale(app()->getLocale())
                ->send(new GameAccountActivationMail(
                    accountId: $recipient->accountId,
                    activationUrl: route('account.activation', [
                        'account' => $recipient->accountId,
                        'token' => $rawToken,
                    ]),
                    expiresInMinutes: $expiresInMinutes,
                ));
        } catch (Throwable $exception) {
            $activation->update(['used_at' => now()]);

            throw $exception;
        }

        return true;
    }
}
