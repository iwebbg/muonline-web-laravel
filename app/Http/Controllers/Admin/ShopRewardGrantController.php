<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantShopRewardRequest;
use App\Models\Administrator;
use App\Models\ShopOffer;
use App\Shop\Actions\GrantShopReward;
use App\Shop\Exceptions\RewardGrantRejected;
use Illuminate\Http\RedirectResponse;

/**
 * Assigns one shop-defined reward to a game account without charging Credits.
 */
final class ShopRewardGrantController extends Controller
{
    /**
     * Creates durable reward ownership or returns a safe operator error.
     */
    public function __invoke(
        GrantShopRewardRequest $request,
        ShopOffer $shopOffer,
        GrantShopReward $grantShopReward,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        try {
            $grantShopReward->execute(
                administrator: $administrator,
                accountId: $request->string('account_id')->toString(),
                offer: $shopOffer,
                reason: $request->string('reason')->trim()->toString(),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (RewardGrantRejected $exception) {
            return back()->withErrors(['reward_grant' => $exception->getMessage()]);
        }

        return back()->with('status', __('ui.admin.shop_reward_granted'));
    }
}
