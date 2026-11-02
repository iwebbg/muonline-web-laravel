<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreVoteClaimRequest;
use App\Models\VoteSite;
use App\VoteRewards\Actions\CreateVoteClaim;
use App\VoteRewards\Exceptions\VoteClaimRejected;
use Illuminate\Http\RedirectResponse;

/**
 * Initiates one vote visit and redirects the account to the configured destination.
 */
final class VoteClaimController extends Controller
{
    /**
     * Creates a pending manual-review claim before leaving the application.
     */
    public function __invoke(
        StoreVoteClaimRequest $request,
        VoteSite $voteSite,
        CreateVoteClaim $createVoteClaim,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $createVoteClaim->execute(
                voteSite: $voteSite,
                accountId: $account->username,
                ipAddress: (string) $request->ip(),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (VoteClaimRejected $exception) {
            return back()->withErrors([
                'voteClaim' => __('ui.votes.errors.'.$exception->reason->value),
            ]);
        }

        return redirect()->away($voteSite->url);
    }
}
