<?php

namespace App\Http\Controllers\Auth;

use App\AccountRegistration\AccountRegistrationBlockReason;
use App\AccountRegistration\Actions\RegisterGameAccount;
use App\AccountRegistration\Exceptions\AccountRegistrationRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Displays and processes public legacy game-account registration.
 */
final class RegisteredAccountController extends Controller
{
    /**
     * Displays the bilingual registration screen for unauthenticated players.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Creates one complete game account and redirects the player to sign in.
     *
     * @throws ValidationException When the account identifier is already used.
     */
    public function store(
        RegisterAccountRequest $request,
        RegisterGameAccount $registerGameAccount,
    ): RedirectResponse {
        try {
            $registerGameAccount->handle(
                username: $request->username(),
                password: $request->password(),
                email: $request->email(),
            );
        } catch (AccountRegistrationRejected $exception) {
            if ($exception->reason === AccountRegistrationBlockReason::AccountAlreadyExists) {
                throw ValidationException::withMessages([
                    'username' => __('ui.registration.account_exists'),
                ]);
            }

            abort($exception->httpStatus, __('ui.registration.unavailable'));
        }

        return redirect()
            ->route('login')
            ->with('status', __('ui.registration.completed'));
    }
}
