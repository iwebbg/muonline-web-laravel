<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmAccountDeletionRequest;
use App\Http\Requests\Account\StoreAccountDeletionRequest;
use App\Mail\AccountDeletionCodeMail;
use App\Models\AccountDeletionRequest;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Issues and verifies privacy deletion requests without deleting legacy data blindly.
 */
final class AccountDeletionRequestController extends Controller
{
    /**
     * Creates the controller with Laravel's application database manager.
     */
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Verifies the current password and sends a short-lived code to the stored email.
     */
    public function store(StoreAccountDeletionRequest $request): RedirectResponse
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        if ($account->email === null || $account->email === '') {
            throw ValidationException::withMessages([
                'currentPassword' => __('ui.account.deletion_email_missing'),
            ]);
        }

        if (! hash_equals($account->getAuthPassword(), $request->currentPassword())) {
            throw ValidationException::withMessages([
                'currentPassword' => __('ui.account.credentials_current_password_incorrect'),
            ]);
        }

        $expiresInMinutes = 15;
        $verificationCode = (string) random_int(100_000, 999_999);

        $deletionRequest = $this->database->transaction(function () use (
            $account,
            $expiresInMinutes,
            $verificationCode,
        ): AccountDeletionRequest {
            AccountDeletionRequest::query()
                ->where('account_id', $account->username)
                ->where('status', 'pending_verification')
                ->update(['status' => 'replaced']);

            return AccountDeletionRequest::query()->create([
                'account_id' => $account->username,
                'email_hash' => hash('sha256', mb_strtolower($account->email)),
                'code_hash' => Hash::make($verificationCode),
                'status' => 'pending_verification',
                'failed_attempts' => 0,
                'expires_at' => now()->addMinutes($expiresInMinutes),
            ]);
        }, 3);

        Mail::to($account->email)
            ->locale(app()->getLocale())
            ->send(new AccountDeletionCodeMail(
                accountId: $account->username,
                verificationCode: $verificationCode,
                expiresInMinutes: $expiresInMinutes,
            ));

        return back()
            ->with('deletionStatus', __('ui.account.deletion_code_sent'))
            ->with('deletionRequestId', $deletionRequest->getKey());
    }

    /**
     * Consumes the email code and records an immutable verified deletion request.
     */
    public function confirm(ConfirmAccountDeletionRequest $request): RedirectResponse
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $verified = $this->database->transaction(function () use ($request, $account): bool {
            $deletionRequest = AccountDeletionRequest::query()
                ->whereKey($request->deletionRequestId())
                ->where('account_id', $account->username)
                ->lockForUpdate()
                ->first();

            if (
                ! $deletionRequest instanceof AccountDeletionRequest
                || $deletionRequest->status !== 'pending_verification'
                || $deletionRequest->expires_at->isPast()
                || $deletionRequest->failed_attempts >= 5
            ) {
                return false;
            }

            if (! Hash::check($request->verificationCode(), $deletionRequest->code_hash)) {
                $deletionRequest->increment('failed_attempts');

                return false;
            }

            $deletionRequest->update([
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            return true;
        }, 3);

        if (! $verified) {
            throw ValidationException::withMessages([
                'verificationCode' => __('ui.account.deletion_code_invalid'),
            ]);
        }

        return redirect()
            ->route('account.security.index')
            ->with('deletionStatus', __('ui.account.deletion_verified'));
    }
}
