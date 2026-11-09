<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CreditEarning\Actions\ExchangeItemsForCredits;
use App\CreditEarning\Exceptions\CreditEarningRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExchangeItemsForCreditsRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Exchanges exact owned character items for Credits from the account page.
 */
final class CreditItemExchangeController extends Controller
{
    /**
     * Executes one validated exchange and returns player-safe feedback.
     */
    public function __invoke(
        ExchangeItemsForCreditsRequest $request,
        ExchangeItemsForCredits $exchangeItemsForCredits,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $exchange = $exchangeItemsForCredits->execute(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                offerPublicId: $request->string('offer_id')->toString(),
                quantity: $request->integer('quantity'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (CreditEarningRejected $exception) {
            return back()->withInput()->withErrors([
                'credit_earning' => __('ui.account.credit_earning_errors.'.$exception->reason->value),
            ]);
        }

        return redirect()
            ->route('account.credits.index', ['section' => 'earn'])
            ->with('status', __('ui.account.item_exchange_completed', [
                'credits' => number_format($exchange->credits_awarded),
            ]));
    }
}
