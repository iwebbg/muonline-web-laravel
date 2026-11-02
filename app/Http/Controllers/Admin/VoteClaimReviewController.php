<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewVoteClaimRequest;
use App\Models\Administrator;
use App\Models\VoteClaim;
use App\VoteRewards\Actions\AwardVoteClaim;
use App\VoteRewards\Actions\RejectVoteClaim;
use App\VoteRewards\Exceptions\VoteClaimRejected;
use Illuminate\Http\RedirectResponse;

/**
 * Applies one authorized manual decision to a pending vote claim.
 */
final class VoteClaimReviewController extends Controller
{
    /**
     * Awards or rejects a claim and reports expected policy failures safely.
     */
    public function __invoke(
        ReviewVoteClaimRequest $request,
        VoteClaim $voteClaim,
        AwardVoteClaim $awardVoteClaim,
        RejectVoteClaim $rejectVoteClaim,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        try {
            if ($request->string('decision')->toString() === 'approve') {
                $awardVoteClaim->execute($voteClaim, $administrator);
                $message = __('ui.admin.vote_claim_rewarded');
            } else {
                $rejectVoteClaim->execute(
                    voteClaim: $voteClaim,
                    administrator: $administrator,
                    reason: $request->string('reason')->toString(),
                );
                $message = __('ui.admin.vote_claim_rejected');
            }
        } catch (VoteClaimRejected $exception) {
            return back()->withErrors([
                'voteClaim' => __('ui.votes.errors.'.$exception->reason->value),
            ]);
        }

        return back()->with('status', $message);
    }
}
