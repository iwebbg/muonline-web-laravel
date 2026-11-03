<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreWebStorageItemRequest;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\StoreWebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\WebStorageContext;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Displays and mutates the authenticated player's exact-item web vault.
 */
final class WebStorageController extends Controller
{
    /**
     * Redirects the legacy storage page to the unified account inventory.
     */
    public function index(Request $request): RedirectResponse
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        return redirect()->route('account.dashboard', ['tab' => 'vaults']);
    }

    /**
     * Moves one exact owned warehouse item into recoverable web storage.
     */
    public function store(
        StoreWebStorageItemRequest $request,
        StoreWebStorageItem $storeWebStorageItem,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $item = $storeWebStorageItem->execute(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                vault: $request->integer('vault'),
                slot: $request->integer('slot'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (WebStorageRejected $exception) {
            return back()->withErrors([
                'webStorage' => __('ui.web_storage.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = $item->status === WebStorageItemStatus::Available
            ? 'ui.web_storage.stored'
            : 'ui.web_storage.store_pending';

        return back()->with('webStorageStatus', __($messageKey));
    }

    /**
     * Withdraws one owned stored item to the account's active warehouse.
     */
    public function destroy(
        Request $request,
        WebStorageItem $webStorageItem,
        WithdrawWebStorageItem $withdrawWebStorageItem,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);
        abort_unless($webStorageItem->storage_context === WebStorageContext::WebVault, 404);

        try {
            $item = $withdrawWebStorageItem->execute(
                accountId: $account->username,
                itemId: (int) $webStorageItem->getKey(),
            );
        } catch (WebStorageRejected $exception) {
            return back()->withErrors([
                'webStorage' => __('ui.web_storage.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = $item->status === WebStorageItemStatus::Withdrawn
            ? 'ui.web_storage.withdrawn'
            : 'ui.web_storage.withdraw_pending';

        return back()->with('webStorageStatus', __($messageKey));
    }
}
