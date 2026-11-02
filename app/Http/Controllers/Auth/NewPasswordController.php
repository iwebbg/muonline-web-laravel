<?php

namespace App\Http\Controllers\Auth;

use App\AccountRecovery\Actions\ResetGameAccountPassword;
use App\AccountRecovery\Exceptions\PasswordRecoveryRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetGameAccountPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Presents and consumes single-use game account password-reset tokens.
 */
final class NewPasswordController extends Controller
{
    /**
     * Displays the legacy-compatible password form for one email token.
     */
    public function create(string $account, string $token): View
    {
        return view('auth.reset-password', [
            'account' => $account,
            'token' => $token,
        ]);
    }

    /**
     * Claims the token and redirects to login after one successful password update.
     */
    public function store(
        ResetGameAccountPasswordRequest $request,
        ResetGameAccountPassword $resetGameAccountPassword,
    ): RedirectResponse {
        try {
            $resetGameAccountPassword->handle(
                accountId: $request->accountId(),
                rawToken: $request->resetToken(),
                newPassword: $request->newPassword(),
            );
        } catch (PasswordRecoveryRejected $exception) {
            return back()
                ->withInput($request->safe()->except([
                    'token',
                    'password',
                    'password_confirmation',
                ]))
                ->withErrors([
                    'token' => __('ui.recovery.reasons.'.$exception->reason->value),
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', __('ui.recovery.password_reset'));
    }
}
