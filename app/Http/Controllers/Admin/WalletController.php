<?php

namespace App\Http\Controllers\Admin;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustWalletRequest;
use App\Models\AccountWallet;
use App\Models\Administrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays wallet history and applies permission-scoped balance adjustments.
 */
final class WalletController extends Controller
{
    /**
     * Displays one exact account wallet and its newest immutable transactions.
     */
    public function index(Request $request): View
    {
        $requestedAccountId = $request->string('account')->trim()->toString();
        $accountId = preg_match('/\A[A-Za-z0-9_-]{4,10}\z/', $requestedAccountId) === 1
            ? $requestedAccountId
            : null;
        $wallet = $accountId === null
            ? null
            : AccountWallet::query()
                ->where('account_id', $accountId)
                ->where('currency', WalletCurrency::Credits)
                ->first();
        $transactions = $wallet === null
            ? collect()
            : $wallet->transactions()
                ->latest('id')
                ->limit(50)
                ->get();

        return view('admin.wallet', [
            'accountId' => $accountId,
            'requestedAccountId' => $requestedAccountId,
            'wallet' => $wallet,
            'transactions' => $transactions,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    /**
     * Applies one credit or debit after all account and balance checks pass.
     */
    public function store(
        AdjustWalletRequest $request,
        AdjustWalletBalance $adjustWalletBalance,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $direction = $request->string('direction')->toString();
        $unsignedAmount = $request->integer('amount');
        $amount = $direction === 'credit'
            ? $unsignedAmount
            : -$unsignedAmount;
        $type = $direction === 'credit'
            ? WalletTransactionType::AdminCredit
            : WalletTransactionType::AdminDebit;
        $accountId = $request->string('account_id')->toString();

        try {
            $adjustWalletBalance->execute(
                accountId: $accountId,
                currency: WalletCurrency::Credits,
                type: $type,
                amount: $amount,
                reason: $request->string('reason')->trim()->toString(),
                administratorId: (int) $administrator->getKey(),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (WalletAdjustmentRejected $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'adjustment' => __('ui.admin.wallet_errors.'.$exception->reason->value),
                ]);
        }

        return redirect()
            ->route('admin.wallet.index', ['account' => $accountId])
            ->with('status', __('ui.admin.wallet_adjusted'));
    }
}
