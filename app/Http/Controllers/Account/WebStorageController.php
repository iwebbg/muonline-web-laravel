<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\WarehouseVault;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreWebStorageItemRequest;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\StoreWebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays and mutates the authenticated player's exact-item web vault.
 */
final class WebStorageController extends Controller
{
    /**
     * Creates the controller with version-aware character and warehouse readers.
     */
    public function __construct(
        private readonly AccountReadRepository $accountReadRepository,
        private readonly ItemStorageRepository $itemStorageRepository,
    ) {}

    /**
     * Displays owned warehouse items and account-scoped web storage history.
     */
    public function index(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $characters = $this->accountReadRepository->charactersFor($account->username);
        $requestedCharacter = $request->string('character')->toString();
        $selectedCharacter = null;

        foreach ($characters as $character) {
            if ($character->name === $requestedCharacter) {
                $selectedCharacter = $character->name;
            }
        }

        $selectedCharacter ??= $characters[0]->name ?? null;
        $warehouseVaults = [];

        if (is_string($selectedCharacter)) {
            $storage = $this->itemStorageRepository->forAccountAndCharacter(
                $account->username,
                $selectedCharacter,
            );
            $warehouseVaults = $storage?->vaults ?? [];
        }

        $storedItems = WebStorageItem::query()
            ->where('account_id', $account->username)
            ->latest('id')
            ->limit(100)
            ->get();

        return view('account.web-storage', [
            'characters' => $characters,
            'selectedCharacter' => $selectedCharacter,
            'warehouseVaults' => $warehouseVaults,
            'storedItems' => $storedItems,
            'idempotencyKeys' => $this->idempotencyKeys($warehouseVaults),
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
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

    /**
     * Creates one independent replay key for every rendered warehouse item form.
     *
     * @param  list<WarehouseVault>  $vaults
     * @return array<int, array<int, string>>
     */
    private function idempotencyKeys(array $vaults): array
    {
        $keys = [];

        foreach ($vaults as $vault) {
            foreach ($vault->items as $item) {
                $keys[$vault->number][$item->slot] = (string) Str::uuid();
            }
        }

        return $keys;
    }
}
