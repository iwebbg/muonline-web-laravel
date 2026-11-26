<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSocialLinkRequest;
use App\Models\SocialLink;
use Illuminate\Http\RedirectResponse;

/** Manages independently editable links from the website settings screen. */
final class SocialLinkController extends Controller
{
    /** Adds another channel, including additional channels on the same network. */
    public function store(StoreSocialLinkRequest $request): RedirectResponse
    {
        SocialLink::query()->create($request->validated());

        return back()->with('status', __('ui.social.saved'));
    }

    /** Updates a link without replacing other administrators' changes. */
    public function update(StoreSocialLinkRequest $request, SocialLink $socialLink): RedirectResponse
    {
        $socialLink->update($request->validated());

        return back()->with('status', __('ui.social.saved'));
    }

    /** Removes one destination under the route's settings permission boundary. */
    public function destroy(SocialLink $socialLink): RedirectResponse
    {
        $socialLink->delete();

        return back()->with('status', __('ui.social.deleted'));
    }
}
