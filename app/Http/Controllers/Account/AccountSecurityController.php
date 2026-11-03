<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays security and privacy controls for the authenticated game account.
 */
final class AccountSecurityController extends Controller
{
    /**
     * Returns the current account state without exposing its password.
     */
    public function __invoke(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        return view('account.security', [
            'account' => $account,
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false),
            'deletionRequest' => AccountDeletionRequest::query()
                ->where('account_id', $account->username)
                ->latest('id')
                ->first(),
        ]);
    }
}
