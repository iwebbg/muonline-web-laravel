<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PurchaseCreditPackageRequest;
use App\Models\CreditPackage;
use App\Payments\Actions\CreateCreditCheckout;
use App\Payments\PaymentProvider;
use Illuminate\Http\RedirectResponse;

/** Starts a hosted checkout for one authenticated account. */
final class CreditPurchaseController extends Controller
{
    public function __invoke(PurchaseCreditPackageRequest $request, CreditPackage $creditPackage, CreateCreditCheckout $checkout): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof GameAccount, 403);
        $payment = $checkout->execute($account, $creditPackage, PaymentProvider::from($request->string('provider')->toString()));

        return redirect()->away((string) $payment->provider_payload['checkout_url']);
    }
}
