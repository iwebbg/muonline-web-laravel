<?php

namespace App\Http\Middleware;

use App\Models\Administrator;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rechecks the persisted administrator identity before every protected administration request.
 */
final class EnsureActiveAdministrator
{
    /**
     * Revokes disabled/deleted identities and legacy sessions without a credential baseline.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');
        $identity = $guard->user();
        $administrator = $identity instanceof Administrator ? $identity->fresh() : null;
        $unversionedSession = $request->session()->has($guard->getName())
            && ! $guard->viaRemember()
            && ! $request->session()->has('password_hash_admin');

        if (! $administrator instanceof Administrator || ! $administrator->is_active || $unversionedSession) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException('Unauthenticated.', ['admin']);
        }

        $guard->setUser($administrator);

        return $next($request);
    }
}
