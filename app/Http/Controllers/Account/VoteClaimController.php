<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreVoteClaimRequest;
use App\Models\VoteSite;
use App\VoteRewards\Actions\AwardVoteClaim;
use App\VoteRewards\Actions\CreateVoteClaim;
use App\VoteRewards\Exceptions\VoteClaimRejected;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Http\RedirectResponse;

/**
 * Initiates one vote visit and redirects the account to the configured destination.
 */
final class VoteClaimController extends Controller
{
    /**
     * Creates a claim and applies its configured reward mode before redirecting.
     */
    public function __invoke(
        StoreVoteClaimRequest $request,
        VoteSite $voteSite,
        CreateVoteClaim $createVoteClaim,
        AwardVoteClaim $awardVoteClaim,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $claim = $createVoteClaim->execute(
                voteSite: $voteSite,
                accountId: $account->username,
                ipAddress: (string) $request->ip(),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );

            if ($voteSite->auto_reward_on_click && $claim->status === VoteClaimStatus::Pending) {
                $awardVoteClaim->executeAutomatically($claim);
            }
        } catch (VoteClaimRejected $exception) {
            return back()->withErrors([
                'voteClaim' => __('ui.votes.errors.'.$exception->reason->value),
            ]);
        }

        return redirect()->away($voteSite->url);
    }
}
