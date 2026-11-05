<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Models\CreditPayment;
use App\Payments\Actions\CompleteCreditPayment;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Reconciles a provider return without trusting browser-supplied state. */
final class CreditPaymentReturnController extends Controller
{
    public function __invoke(Request $request, CreditPayment $creditPayment, PaymentGatewayManager $gateways, CompleteCreditPayment $complete): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof GameAccount && hash_equals($creditPayment->account_id, $account->username), 404);
        abort_unless($request->string('provider')->toString() === $creditPayment->provider->value, 404);

        if ($creditPayment->credited_at !== null) {
            return redirect()->route('account.credits.index')->with('status', __('ui.account.credits_payment_completed'));
        }

        $confirmation = $gateways->for($creditPayment->provider)->confirmReturn($creditPayment, $request);

        if (($confirmation['paid'] ?? false) !== true && ($confirmation['refunded'] ?? false) !== true) {
            return redirect()->route('account.credits.index')->with('status', __('ui.account.credits_payment_pending'));
        }

        $complete->execute($creditPayment, $confirmation);

        return redirect()->route('account.credits.index')->with('status', __('ui.account.credits_payment_completed'));
    }
}
