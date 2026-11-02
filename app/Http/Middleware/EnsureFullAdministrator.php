<?php

namespace App\Http\Middleware;

use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts sensitive identity management to full application administrators.
 */
final class EnsureFullAdministrator
{
    /**
     * Rejects Game Masters even when they hold every granular capability.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $administrator = $request->user('admin');

        abort_unless(
            $administrator instanceof Administrator
            && $administrator->role === AdministratorRole::Administrator,
            403,
        );

        return $next($request);
    }
}
