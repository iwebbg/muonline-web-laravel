<?php

namespace App\Http\Controllers\Account;

use App\AccountCredentials\AccountCredentialBlockReason;
use App\AccountCredentials\Actions\UpdateAccountCredentials;
use App\AccountCredentials\Exceptions\AccountCredentialUpdateRejected;
use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateAccountCredentialsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Processes authenticated email and password changes for game accounts.
 */
final class AccountCredentialController extends Controller
{
    /**
     * Updates the current account, then rotates its authenticated session identifier.
     *
     * @throws ValidationException When the current password or proposed state is invalid.
     */
    public function update(
        UpdateAccountCredentialsRequest $request,
        UpdateAccountCredentials $updateAccountCredentials,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $updateAccountCredentials->handle(
                accountId: $account->username,
                currentPassword: $request->currentPassword(),
                email: $request->email(),
                newPassword: $request->newPassword(),
            );
        } catch (AccountCredentialUpdateRejected $exception) {
            if ($exception->reason === AccountCredentialBlockReason::CurrentPasswordInvalid) {
                throw ValidationException::withMessages([
                    'currentPassword' => __('ui.account.credentials_current_password_incorrect'),
                ]);
            }

            if ($exception->reason === AccountCredentialBlockReason::NoChanges) {
                throw ValidationException::withMessages([
                    'email' => __('ui.account.credentials_no_changes'),
                ]);
            }

            abort($exception->httpStatus, __('ui.account.credentials_unavailable'));
        }

        $request->session()->regenerate();

        return redirect()
            ->route('account.security.index')
            ->with('accountStatus', __('ui.account.credentials_updated'));
    }
}
