<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVoteSiteRequest;
use App\Http\Requests\Admin\UpdateVoteSiteRequest;
use App\Models\VoteSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manages vote-link destinations and their future reward policies.
 */
final class VoteSiteController extends Controller
{
    /**
     * Displays configured destinations without loading historical claims.
     */
    public function index(): View
    {
        $sites = VoteSite::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.vote-links', [
            'sites' => $sites,
        ]);
    }

    /**
     * Creates one destination using the validated reward policy.
     */
    public function store(StoreVoteSiteRequest $request): RedirectResponse
    {
        VoteSite::query()->create($request->voteSiteData());

        return back()->with('status', __('ui.admin.vote_site_created'));
    }

    /**
     * Updates future claims without changing existing reward snapshots.
     */
    public function update(
        UpdateVoteSiteRequest $request,
        VoteSite $voteSite,
    ): RedirectResponse {
        $voteSite->update($request->voteSiteData());

        return back()->with('status', __('ui.admin.vote_site_updated'));
    }

    /**
     * Disables and soft-deletes a destination while preserving its audit history.
     */
    public function destroy(VoteSite $voteSite): RedirectResponse
    {
        DB::transaction(function () use ($voteSite): void {
            $voteSite->update(['enabled' => false]);
            $voteSite->delete();
        });

        return back()->with('status', __('ui.admin.vote_site_deleted'));
    }
}
