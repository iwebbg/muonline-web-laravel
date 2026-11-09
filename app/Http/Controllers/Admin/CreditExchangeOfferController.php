<?php

namespace App\Http\Controllers\Admin;

use App\Game\Contracts\ItemCatalog;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCreditExchangeOfferRequest;
use App\Http\Requests\Admin\UpdateCreditExchangeOfferRequest;
use App\Models\Administrator;
use App\Models\CreditExchangeOffer;
use App\Models\CreditExchangeOfferRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages exact item-signature offers that award Credits to players.
 */
final class CreditExchangeOfferController extends Controller
{
    /**
     * Creates one active-profile offer and its immutable creation revision.
     */
    public function store(
        StoreCreditExchangeOfferRequest $request,
        ItemCatalog $catalog,
        GameVersionProfile $profile,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);
        $attributes = $request->offerData($catalog, $profile);

        DB::transaction(function () use ($attributes, $administrator): void {
            $offer = CreditExchangeOffer::query()->create([
                ...$attributes,
                'public_id' => (string) Str::uuid(),
                'revision' => 1,
                'updated_by_administrator_id' => $administrator->getKey(),
            ]);

            CreditExchangeOfferRevision::query()->create([
                'credit_exchange_offer_id' => $offer->getKey(),
                'revision' => 1,
                'action' => 'created',
                'before_snapshot' => null,
                'after_snapshot' => $offer->snapshot(),
                'changed_by_administrator_id' => $administrator->getKey(),
            ]);
        });

        return back()->with('status', __('ui.admin.credit_exchange_offer_created'));
    }

    /**
     * Updates one active-profile offer and appends a complete audit revision.
     */
    public function update(
        UpdateCreditExchangeOfferRequest $request,
        CreditExchangeOffer $creditExchangeOffer,
        ItemCatalog $catalog,
        GameVersionProfile $profile,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);
        abort_unless($creditExchangeOffer->game_profile_key === $profile->key(), 404);
        $attributes = $request->offerData($catalog, $profile);

        DB::transaction(function () use ($attributes, $administrator, $creditExchangeOffer): void {
            $lockedOffer = CreditExchangeOffer::query()
                ->lockForUpdate()
                ->findOrFail($creditExchangeOffer->getKey());
            $beforeSnapshot = $lockedOffer->snapshot();
            $revision = $lockedOffer->revision + 1;

            $lockedOffer->update([
                ...$attributes,
                'revision' => $revision,
                'updated_by_administrator_id' => $administrator->getKey(),
            ]);

            CreditExchangeOfferRevision::query()->create([
                'credit_exchange_offer_id' => $lockedOffer->getKey(),
                'revision' => $revision,
                'action' => 'updated',
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $lockedOffer->refresh()->snapshot(),
                'changed_by_administrator_id' => $administrator->getKey(),
            ]);
        });

        return back()->with('status', __('ui.admin.credit_exchange_offer_updated'));
    }

    /**
     * Archives one offer while preserving its final immutable revision.
     */
    public function destroy(
        Request $request,
        CreditExchangeOffer $creditExchangeOffer,
        GameVersionProfile $profile,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);
        abort_unless($creditExchangeOffer->game_profile_key === $profile->key(), 404);

        DB::transaction(function () use ($administrator, $creditExchangeOffer): void {
            $lockedOffer = CreditExchangeOffer::query()
                ->lockForUpdate()
                ->findOrFail($creditExchangeOffer->getKey());
            $beforeSnapshot = $lockedOffer->snapshot();
            $revision = $lockedOffer->revision + 1;
            $lockedOffer->update([
                'enabled' => false,
                'archived_at' => now(),
                'revision' => $revision,
                'updated_by_administrator_id' => $administrator->getKey(),
            ]);

            CreditExchangeOfferRevision::query()->create([
                'credit_exchange_offer_id' => $lockedOffer->getKey(),
                'revision' => $revision,
                'action' => 'archived',
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $lockedOffer->refresh()->snapshot(),
                'changed_by_administrator_id' => $administrator->getKey(),
            ]);
        });

        return back()->with('status', __('ui.admin.credit_exchange_offer_archived'));
    }
}
