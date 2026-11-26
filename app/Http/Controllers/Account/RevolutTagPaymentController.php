<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Models\CreditPayment;
use App\Payments\Gateways\RevolutTagGateway;
use App\Payments\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Displays immutable transfer instructions only to the purchasing account. */
final class RevolutTagPaymentController extends Controller
{
    /** Shows the saved recipient, package amount and required account reference. */
    public function __invoke(
        Request $request,
        CreditPayment $creditPayment,
        RevolutTagGateway $gateway,
    ): View {
        $account = $request->user();

        abort_unless(
            $account instanceof GameAccount
                && hash_equals($creditPayment->account_id, $account->username)
                && $creditPayment->provider === PaymentProvider::RevolutTag,
            404,
        );

        return view('account.revolut-tag', [
            'payment' => $creditPayment,
            'paymentUrl' => $gateway->paymentUrl($creditPayment),
        ]);
    }
}
