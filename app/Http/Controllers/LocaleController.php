<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Stores a supported interface language and returns to the previous page.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $supportedLocales = config('localization.supported', []);

        abort_unless(array_key_exists($locale, $supportedLocales), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
