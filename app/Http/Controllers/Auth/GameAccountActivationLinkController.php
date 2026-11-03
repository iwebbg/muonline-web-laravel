<?php

namespace App\Http\Controllers\Auth;

use App\AccountRegistration\Actions\SendAccountActivationLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendAccountActivationLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Presents an enumeration-safe form for replacing lost or expired activation links.
 */
final class GameAccountActivationLinkController extends Controller
{
    /**
     * Displays the account activation resend form.
     */
    public function create(): View
    {
        return view('auth.activate-account');
    }

    /**
     * Attempts delivery and always returns the same public confirmation message.
     */
    public function store(
        SendAccountActivationLinkRequest $request,
        SendAccountActivationLink $sendAccountActivationLink,
    ): RedirectResponse {
        $sendAccountActivationLink->handle(
            accountId: $request->accountId(),
            email: $request->emailAddress(),
        );

        return back()->with('status', __('ui.activation.link_sent'));
    }
}
