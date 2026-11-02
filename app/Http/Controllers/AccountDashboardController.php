<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Contracts\AccountReadRepository;
use App\Models\AccountWallet;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays private character data for the authenticated MuOnline account.
 */
final class AccountDashboardController extends Controller
{
    /**
     * Creates the controller with the version-aware private account reader.
     */
    public function __construct(
        private readonly AccountReadRepository $accountReadRepository,
    ) {}

    /**
     * Displays only characters owned by the current authenticated account.
     */
    public function __invoke(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $characterEntries = $this->accountReadRepository->charactersFor($account->username);
        $characters = [];

        foreach ($characterEntries as $characterEntry) {
            $characters[] = $characterEntry->toArray();
        }

        $wallet = AccountWallet::query()
            ->where('account_id', $account->username)
            ->where('currency', WalletCurrency::Credits)
            ->first();
        $walletTransactions = $wallet === null
            ? collect()
            : $wallet->transactions()
                ->latest('id')
                ->limit(20)
                ->get();

        return view('account.dashboard', [
            'account' => $account,
            'characters' => $characters,
            'walletBalance' => $wallet?->balance ?? 0,
            'walletTransactions' => $walletTransactions,
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }
}
