<?php

namespace App\Http\Controllers\Admin;

use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmRevolutTagPaymentRequest;
use App\Models\Administrator;
use App\Models\CreditPayment;
use App\Payments\Actions\ConfirmRevolutTagPayment;
use Illuminate\Http\RedirectResponse;

/** Confirms transfers reviewed outside the website by an economy administrator. */
final class RevolutTagPaymentController extends Controller
{
    /** Applies the receipt against server-owned payment terms and returns to history. */
    public function __invoke(
        ConfirmRevolutTagPaymentRequest $request,
        CreditPayment $creditPayment,
        ConfirmRevolutTagPayment $confirmation,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        try {
            $confirmation->execute($creditPayment, $administrator, $request->string('reference')->toString());
        } catch (WalletAdjustmentRejected $exception) {
            return back()->withErrors([
                'payment' => __('ui.admin.wallet_errors.'.$exception->reason->value),
            ]);
        }

        return back()->with('status', __('ui.revolut_tag.confirmed'));
    }
}
