<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Creates and destroys secure player sessions backed by MuOnline accounts.
 */
final class AuthenticatedSessionController extends Controller
{
    /**
     * Displays the bilingual player login screen.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticates a legacy account and rotates the session identifier.
     *
     * @throws ValidationException When the credentials or account state are invalid.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only([
            'username',
            'password',
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'username' => __('ui.auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard', absolute: false));
    }

    /**
     * Ends the player session and rotates the cross-site request forgery token.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
