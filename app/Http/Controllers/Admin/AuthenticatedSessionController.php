<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\Administrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Creates and destroys isolated sessions for application administrators.
 */
final class AuthenticatedSessionController extends Controller
{
    /**
     * Displays the separate administrator login screen.
     */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Authenticates an active administrator and records the successful login time.
     *
     * @throws ValidationException When the credentials or administrator state are invalid.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            ...$request->safe()->only([
                'email',
                'password',
            ]),
            'is_active' => true,
        ];

        if (! Auth::guard('admin')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('ui.admin_auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $administrator = Auth::guard('admin')->user();

        if ($administrator instanceof Administrator) {
            $administrator->forceFill([
                'last_login_at' => now(),
            ])->save();
        }

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    /**
     * Ends the administrator session and invalidates all data in that browser session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
