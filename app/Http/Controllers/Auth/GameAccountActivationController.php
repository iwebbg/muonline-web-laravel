<?php

namespace App\Http\Controllers\Auth;

use App\AccountRegistration\Actions\ActivateGameAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Consumes public single-use links that activate blocked game accounts.
 */
final class GameAccountActivationController extends Controller
{
    /**
     * Activates the exact account or returns a non-disclosing invalid-link response.
     */
    public function __invoke(
        string $account,
        string $token,
        ActivateGameAccount $activateGameAccount,
    ): RedirectResponse {
        abort_unless(
            $activateGameAccount->handle($account, $token),
            404,
            __('ui.activation.invalid'),
        );

        return redirect()
            ->route('login')
            ->with('status', __('ui.activation.completed'));
    }
}
