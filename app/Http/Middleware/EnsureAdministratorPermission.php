<?php

namespace App\Http\Middleware;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts one administrator route to identities with an explicit capability.
 */
final class EnsureAdministratorPermission
{
    /**
     * Rejects authenticated identities that do not own the required capability.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $permission,
    ): Response {
        $administrator = $request->user('admin');
        $requiredPermission = AdministratorPermission::tryFrom($permission);

        abort_unless(
            $administrator instanceof Administrator
            && $requiredPermission instanceof AdministratorPermission
            && $administrator->hasPermission($requiredPermission),
            403,
        );

        return $next($request);
    }
}
