<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterVoteClaimsRequest;
use App\Models\VoteClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Displays the searchable administrator vote history and review queue.
 */
final class VoteClaimController extends Controller
{
    /**
     * Returns filtered claims in bounded pages without loading vote-link forms.
     */
    public function index(FilterVoteClaimsRequest $request): View
    {
        $search = $request->search();
        $status = $request->status();
        $dateFrom = $request->dateFrom();
        $dateTo = $request->dateTo();
        $claimsQuery = VoteClaim::query()
            ->with(['site', 'reviewer']);

        if ($search !== '') {
            $searchPattern = '%'.$search.'%';

            $claimsQuery->where(function (Builder $claimQuery) use ($searchPattern): void {
                $claimQuery
                    ->whereLike('account_id', $searchPattern)
                    ->orWhereHas('site', function (Builder $siteQuery) use ($searchPattern): void {
                        $siteQuery
                            ->whereLike('name_en', $searchPattern)
                            ->orWhereLike('name_bg', $searchPattern);
                    });
            });
        }

        if ($status !== null) {
            $claimsQuery->where('status', $status);
        }

        if ($dateFrom !== null) {
            $claimsQuery->where('initiated_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $claimsQuery->where('initiated_at', '<', $dateTo);
        }

        $claims = $claimsQuery
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vote-claims', [
            'claims' => $claims,
            'claimFilters' => [
                'search' => $search,
                'status' => $status?->value ?? '',
                'dateFrom' => $request->string('date_from')->toString(),
                'dateTo' => $request->string('date_to')->toString(),
            ],
        ]);
    }
}
