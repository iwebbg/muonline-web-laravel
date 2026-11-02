<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PurchaseVipRequest;
use App\Vip\Actions\PurchaseCharacterVip;
use App\Vip\Exceptions\VipPurchaseRejected;
use App\Vip\VipSyncStatus;
use Illuminate\Http\RedirectResponse;

/**
 * Purchases character VIP time for the authenticated MuOnline account.
 */
final class VipPurchaseController extends Controller
{
    /**
     * Applies one idempotent purchase and reports its synchronization outcome.
     */
    public function __invoke(
        PurchaseVipRequest $request,
        PurchaseCharacterVip $purchaseCharacterVip,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $purchase = $purchaseCharacterVip->execute(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                planId: $request->integer('vip_plan_id'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (VipPurchaseRejected $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'vip' => __('ui.account.vip_errors.'.$exception->reason->value),
                ]);
        }

        $entitlement = $purchase->entitlement()->firstOrFail();
        $messageKey = $entitlement->sync_status === VipSyncStatus::Synced
            ? 'ui.account.vip_purchase_completed'
            : 'ui.account.vip_purchase_pending';

        return back()->with('accountStatus', __($messageKey));
    }
}
