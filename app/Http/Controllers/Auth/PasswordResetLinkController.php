<?php

namespace App\Http\Controllers\Auth;

use App\AccountRecovery\Actions\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPasswordResetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Presents enumeration-safe game account password-reset link requests.
 */
final class PasswordResetLinkController extends Controller
{
    /**
     * Displays the account and email verification form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Attempts delivery and always returns the same public confirmation message.
     */
    public function store(
        RequestPasswordResetRequest $request,
        SendPasswordResetLink $sendPasswordResetLink,
    ): RedirectResponse {
        $sendPasswordResetLink->handle(
            accountId: $request->accountId(),
            email: $request->emailAddress(),
        );

        return back()->with('status', __('ui.recovery.link_sent'));
    }
}
