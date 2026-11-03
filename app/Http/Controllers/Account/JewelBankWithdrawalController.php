<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\WithdrawJewelBatchRequest;
use App\JewelBank\Actions\WithdrawJewelBatch;
use App\Models\WebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Http\RedirectResponse;

/**
 * Handles grouped physical-jewel withdrawals for one authenticated account.
 */
final class JewelBankWithdrawalController extends Controller
{
    /**
     * Withdraws the requested verified quantity and reports its delivery state.
     */
    public function store(
        WithdrawJewelBatchRequest $request,
        WithdrawJewelBatch $withdrawJewelBatch,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $withdrawnItems = $withdrawJewelBatch->execute(
                accountId: $account->username,
                jewelCode: $request->string('jewel_code')->toString(),
                jewelLevel: $request->integer('jewel_level'),
                quantity: $request->integer('quantity'),
                destinationLocation: ItemStorageLocation::from(
                    $request->string('destination_location')->toString(),
                ),
                destinationCharacterName: $request->string('character_name')->toString() ?: null,
            );
        } catch (WebStorageRejected $exception) {
            return back()->withErrors([
                'jewelBank' => __('ui.web_storage.errors.'.$exception->reason->value),
            ]);
        }

        $allItemsWithdrawn = collect($withdrawnItems)->every(
            static fn (WebStorageItem $item): bool => $item->status === WebStorageItemStatus::Withdrawn,
        );
        $messageKey = $allItemsWithdrawn
            ? 'ui.jewel_bank.withdrawn'
            : 'ui.jewel_bank.withdraw_pending';

        return back()->with('jewelBankStatus', __($messageKey, [
            'quantity' => count($withdrawnItems),
        ]));
    }
}
