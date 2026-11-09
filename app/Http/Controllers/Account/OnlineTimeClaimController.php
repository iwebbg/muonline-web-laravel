<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CreditEarning\Actions\ClaimOnlineTimeCredits;
use App\CreditEarning\Exceptions\CreditEarningRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ClaimOnlineTimeCreditsRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Claims accumulated account-level online-time Credits.
 */
final class OnlineTimeClaimController extends Controller
{
    /**
     * Transfers all claimable Credits and returns player-safe feedback.
     */
    public function __invoke(
        ClaimOnlineTimeCreditsRequest $request,
        ClaimOnlineTimeCredits $claimOnlineTimeCredits,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $claim = $claimOnlineTimeCredits->execute(
                accountId: $account->username,
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (CreditEarningRejected $exception) {
            return back()->withErrors([
                'credit_earning' => __('ui.account.credit_earning_errors.'.$exception->reason->value),
            ]);
        }

        return redirect()
            ->route('account.credits.index', ['section' => 'earn'])
            ->with('status', __('ui.account.online_time_claim_completed', [
                'credits' => number_format($claim->credits_claimed),
            ]));
    }
}
