<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Market\Actions\ReturnMarketListing;
use App\Market\Actions\SynchronizeMarketListing;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingStatus;
use App\Models\Administrator;
use App\Models\MarketAdminAction;
use App\Models\MarketListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Provides permission-scoped operational recovery for player-market escrow.
 */
final class MarketController extends Controller
{
    /**
     * Displays filtered escrow state, health totals and recent recovery actions.
     */
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $supportedStatuses = array_column(MarketListingStatus::cases(), 'value');
        $selectedStatus = in_array($status, $supportedStatuses, true) ? $status : null;
        $search = trim($request->string('search')->toString());
        $listings = MarketListing::query()
            ->when(
                $selectedStatus !== null,
                fn (Builder $query): Builder => $query->where('status', $selectedStatus),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('public_id', 'like', '%'.$search.'%')
                        ->orWhere('seller_account_id', 'like', '%'.$search.'%')
                        ->orWhere('buyer_account_id', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate(40)
            ->withQueryString();
        $statusTotals = MarketListing::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $recentActions = MarketAdminAction::query()
            ->with(['administrator:id,name', 'listing:id,public_id'])
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.market', [
            'listings' => $listings,
            'statusTotals' => $statusTotals,
            'recentActions' => $recentActions,
            'selectedStatus' => $selectedStatus,
            'search' => $search,
            'statuses' => MarketListingStatus::cases(),
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Retries one pending extraction, buyer delivery or seller return projection.
     */
    public function retry(
        Request $request,
        MarketListing $marketListing,
        SynchronizeMarketListing $synchronizeMarketListing,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        if (! in_array(
            $marketListing->status,
            [
                MarketListingStatus::Extracting,
                MarketListingStatus::DeliveryPending,
                MarketListingStatus::ReturnPending,
            ],
            true,
        )) {
            return back()->withErrors([
                'market' => __('ui.admin.market_retry_unavailable'),
            ]);
        }

        $beforeStatus = $marketListing->status->value;
        $synchronized = $synchronizeMarketListing->execute($marketListing);
        $marketListing->refresh();
        $this->recordAction(
            administrator: $administrator,
            listing: $marketListing,
            action: 'retry_sync',
            beforeStatus: $beforeStatus,
            outcome: $synchronized ? 'completed' : 'pending',
        );

        return back()->with(
            'status',
            $synchronized
                ? __('ui.admin.market_retry_completed')
                : __('ui.admin.market_retry_pending'),
        );
    }

    /**
     * Removes one active unsold listing and attempts return to its seller.
     */
    public function returnItem(
        Request $request,
        MarketListing $marketListing,
        ReturnMarketListing $returnMarketListing,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $beforeStatus = $marketListing->status->value;

        try {
            $returnedListing = $returnMarketListing->execute(
                accountId: $marketListing->seller_account_id,
                listingId: (int) $marketListing->getKey(),
            );
        } catch (MarketListingRejected $exception) {
            $this->recordAction(
                administrator: $administrator,
                listing: $marketListing,
                action: 'force_return',
                beforeStatus: $beforeStatus,
                outcome: 'failed',
                failureCode: $exception->reason->value,
            );

            return back()->withErrors([
                'market' => __('ui.market.errors.'.$exception->reason->value),
            ]);
        }

        $completed = $returnedListing->status === MarketListingStatus::Returned;
        $this->recordAction(
            administrator: $administrator,
            listing: $returnedListing,
            action: 'force_return',
            beforeStatus: $beforeStatus,
            outcome: $completed ? 'completed' : 'pending',
        );

        return back()->with(
            'status',
            $completed
                ? __('ui.admin.market_return_completed')
                : __('ui.admin.market_return_pending'),
        );
    }

    /**
     * Persists one immutable administrator recovery record with safe context only.
     */
    private function recordAction(
        Administrator $administrator,
        MarketListing $listing,
        string $action,
        string $beforeStatus,
        string $outcome,
        ?string $failureCode = null,
    ): void {
        MarketAdminAction::query()->create([
            'market_listing_id' => $listing->getKey(),
            'administrator_id' => $administrator->getKey(),
            'action' => $action,
            'before_status' => $beforeStatus,
            'after_status' => $listing->status->value,
            'outcome' => $outcome,
            'failure_code' => $failureCode ?? $listing->last_sync_error,
            'context_snapshot' => [
                'public_id' => $listing->public_id,
                'seller_account_id' => $listing->seller_account_id,
                'buyer_account_id' => $listing->buyer_account_id,
                'sync_attempts' => $listing->sync_attempts,
            ],
        ]);
    }
}
