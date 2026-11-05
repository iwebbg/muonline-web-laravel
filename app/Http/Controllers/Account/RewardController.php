<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Models\AccountReward;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\FulfillAccountReward;
use App\Shop\Actions\OpenAccountReward;
use App\Shop\Exceptions\RewardOpenRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays and resolves rewards owned by the authenticated account.
 */
final class RewardController extends Controller
{
    /**
     * Returns the account's unopened, pending and delivered reward history.
     */
    public function index(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $rewards = AccountReward::query()
            ->where('account_id', $account->username)
            ->latest('id')
            ->paginate(20);

        return view('account.rewards', ['rewards' => $rewards]);
    }

    /**
     * Opens one owned mystery box and attempts immediate safe delivery.
     */
    public function open(
        Request $request,
        AccountReward $accountReward,
        OpenAccountReward $openAccountReward,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $reward = $openAccountReward->execute($account->username, $accountReward);
        } catch (RewardOpenRejected) {
            abort(404);
        }

        $message = $reward->status === AccountRewardStatus::Claimed && $reward->last_delivery_error === null
            ? __('ui.rewards.opened_and_delivered')
            : __('ui.rewards.opened_delivery_pending');

        return back()->with('status', $message);
    }

    /**
     * Retries delivery of one already revealed reward without changing its item.
     */
    public function deliver(
        Request $request,
        AccountReward $accountReward,
        FulfillAccountReward $fulfillAccountReward,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless(
            $account instanceof GameAccount && $accountReward->account_id === $account->username,
            404,
        );

        $delivered = $fulfillAccountReward->execute($accountReward);

        return back()->with(
            $delivered ? 'status' : 'error',
            $delivered ? __('ui.rewards.delivered') : __('ui.rewards.delivery_pending'),
        );
    }
}
