<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Applies the visitor's supported session locale to the current request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $defaultLocale = config('app.locale');
        $supportedLocales = config('localization.supported');
        $requestedLocale = $request->session()->get('locale', $defaultLocale);
        $locale = in_array($requestedLocale, $supportedLocales, true)
            ? $requestedLocale
            : config('app.fallback_locale');

        App::setLocale($locale);

        return $next($request);
    }
}
