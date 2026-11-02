<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Models\VoteClaim;
use App\Models\VoteSite;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays account-scoped vote destinations, cooldowns and claim history.
 */
final class VoteController extends Controller
{
    /**
     * Builds the vote cards without exposing claims owned by another account.
     */
    public function __invoke(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $sites = VoteSite::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $claims = VoteClaim::query()
            ->with('site')
            ->where('account_id', $account->username)
            ->latest('id')
            ->limit(100)
            ->get();
        $voteCards = [];

        foreach ($sites as $site) {
            $siteClaims = $claims->where('vote_site_id', $site->getKey());
            $pendingClaim = $siteClaims
                ->first(fn (VoteClaim $claim): bool => $claim->status === VoteClaimStatus::Pending
                    && $claim->expires_at->isFuture());
            $lastRewardedClaim = $siteClaims
                ->first(fn (VoteClaim $claim): bool => $claim->status === VoteClaimStatus::Rewarded);
            $availableAt = $lastRewardedClaim?->rewarded_at?->addHours($site->cooldown_hours);
            $isCoolingDown = $availableAt?->isFuture() ?? false;

            $voteCards[] = [
                'site' => $site,
                'pendingClaim' => $pendingClaim,
                'availableAt' => $isCoolingDown ? $availableAt : null,
                'canInitiate' => $pendingClaim === null && ! $isCoolingDown,
                'idempotencyKey' => (string) Str::uuid(),
            ];
        }

        return view('votes.index', [
            'voteCards' => $voteCards,
            'claims' => $claims->take(50),
        ]);
    }
}
