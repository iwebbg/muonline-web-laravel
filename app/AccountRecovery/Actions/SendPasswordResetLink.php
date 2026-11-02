<?php

namespace App\AccountRecovery\Actions;

use App\AccountCredentials\AccountCredentialStatus;
use App\Game\Contracts\AccountPasswordRecoveryRepository;
use App\Mail\GameAccountPasswordResetMail;
use App\Models\AccountPasswordRecoveryOperation;
use App\Models\GameAccountPasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues a hashed recovery token without revealing whether an account matched.
 */
final readonly class SendPasswordResetLink
{
    /**
     * Creates the action with the version-specific account lookup boundary.
     */
    public function __construct(
        private AccountPasswordRecoveryRepository $accountPasswordRecoveryRepository,
    ) {}

    /**
     * Sends a reset link only for an exact account/email pair and always returns silently.
     */
    public function handle(string $accountId, string $email): void
    {
        $expiresInMinutes = max(5, min(
            (int) config('game.password_reset_ttl_minutes', 60),
            1_440,
        ));
        $operation = AccountPasswordRecoveryOperation::query()->create([
            'account_id' => $accountId,
            'operation_type' => 'link_requested',
            'status' => AccountCredentialStatus::Pending,
            'settings_snapshot' => [
                'expires_in_minutes' => $expiresInMinutes,
                'password_scheme' => (string) config('game.account_password_scheme'),
            ],
        ]);
        $recipient = $this->accountPasswordRecoveryRepository->findRecipient(
            accountId: $accountId,
            email: $email,
        );

        if ($recipient === null) {
            $operation->update([
                'status' => AccountCredentialStatus::Completed,
                'after_snapshot' => [
                    'recipient_matched' => false,
                    'message_dispatched' => false,
                ],
                'completed_at' => now(),
            ]);

            return;
        }

        $rawToken = Str::random(64);
        $token = GameAccountPasswordReset::query()->create([
            'account_id' => $recipient->accountId,
            'token_hash' => hash('sha256', $rawToken),
            'email_hash' => hash('sha256', Str::lower($recipient->email)),
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);

        GameAccountPasswordReset::query()
            ->where('account_id', $recipient->accountId)
            ->whereNull('used_at')
            ->where('id', '!=', $token->getKey())
            ->update(['used_at' => now()]);

        try {
            Mail::to($recipient->email)
                ->locale(app()->getLocale())
                ->send(new GameAccountPasswordResetMail(
                    accountId: $recipient->accountId,
                    resetUrl: route('password.reset', [
                        'account' => $recipient->accountId,
                        'token' => $rawToken,
                    ]),
                    expiresInMinutes: $expiresInMinutes,
                ));
        } catch (Throwable $exception) {
            $token->update(['used_at' => now()]);
            $operation->update([
                'status' => AccountCredentialStatus::Failed,
                'failure_code' => 'mail_delivery_failed',
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $operation->update([
            'status' => AccountCredentialStatus::Completed,
            'after_snapshot' => [
                'recipient_matched' => true,
                'message_dispatched' => true,
            ],
            'completed_at' => now(),
        ]);
    }
}
