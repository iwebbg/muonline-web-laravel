<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects configured demo installations from persistent website changes.
 */
final class EnforceDemoMode
{
    private const ACCOUNT_POLICY = 'account';

    private const BLOCKED_POLICY = 'blocked';

    private const UNSAFE_METHOD_POLICY = 'unsafe';

    /**
     * Allows the request only when it satisfies the selected demo policy.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $policy = self::BLOCKED_POLICY,
    ): Response {
        if (! (bool) config('demo.enabled', false)) {
            return $next($request);
        }

        if ($this->allowsRequest($request, $policy)) {
            return $next($request);
        }

        return $this->deniedResponse($request);
    }

    /**
     * Determines whether the selected demo policy permits the current request.
     */
    private function allowsRequest(Request $request, string $policy): bool
    {
        return match ($policy) {
            self::UNSAFE_METHOD_POLICY => $request->isMethodSafe()
                || $request->routeIs('admin.logout'),
            self::ACCOUNT_POLICY => $request->isMethodSafe()
                || $request->routeIs('logout', 'account.*-preview')
                || $this->isAllowedDemoAccount($request->user()),
            self::BLOCKED_POLICY => false,
            default => false,
        };
    }

    /**
     * Checks the authenticated game identity against the configured demo allow-list.
     */
    private function isAllowedDemoAccount(?Authenticatable $account): bool
    {
        if (! $account instanceof Authenticatable) {
            return false;
        }

        $accountId = $account->getAuthIdentifier();
        $allowedAccountIds = config('demo.account_ids', []);

        if (! is_string($accountId) || ! is_array($allowedAccountIds)) {
            return false;
        }

        return in_array(strtolower($accountId), $allowedAccountIds, true);
    }

    /**
     * Returns a localized demo restriction for browser and JSON clients.
     */
    private function deniedResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.errors.demo.description'),
            ], 403);
        }

        return response()->view('errors.demo-mode', status: 403);
    }
}
