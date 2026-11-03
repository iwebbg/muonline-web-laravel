<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Http\Controllers\Controller;
use App\Models\AccountWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays the authenticated account's Credits balance and ledger history.
 */
final class CreditController extends Controller
{
    /**
     * Returns an ownership-scoped, paginated Credits ledger.
     */
    public function __invoke(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $wallet = AccountWallet::query()
            ->where('account_id', $account->username)
            ->where('currency', WalletCurrency::Credits)
            ->first();
        $transactions = WalletTransaction::query()
            ->where('account_id', $account->username)
            ->where('currency', WalletCurrency::Credits)
            ->latest('id')
            ->paginate(25);

        return view('account.credits', [
            'balance' => $wallet?->balance ?? 0,
            'transactions' => $transactions,
        ]);
    }
}
