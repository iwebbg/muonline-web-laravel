<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreJewelRequest;
use App\JewelBank\Actions\StoreJewel;
use App\JewelBank\JewelCatalog;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays and mutates the physical-item-backed player Jewel Bank.
 */
final class JewelBankController extends Controller
{
    /**
     * Creates the controller with version-aware character and storage readers.
     */
    public function __construct(
        private readonly AccountReadRepository $accountReadRepository,
        private readonly ItemStorageRepository $itemStorageRepository,
        private readonly JewelCatalog $jewelCatalog,
    ) {}

    /**
     * Displays physical jewel counts, entries and eligible warehouse items.
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
        $warehouseJewels = [];

        if (is_string($selectedCharacter)) {
            $storage = $this->itemStorageRepository->forAccountAndCharacter(
                $account->username,
                $selectedCharacter,
            );

            foreach ($storage?->vaults ?? [] as $vault) {
                foreach ($vault->items as $item) {
                    if ($this->jewelCatalog->accepts($item)) {
                        $warehouseJewels[] = [
                            'vault' => $vault->number,
                            'item' => $item,
                            'idempotencyKey' => (string) Str::uuid(),
                        ];
                    }
                }
            }
        }

        $storedJewels = WebStorageItem::query()
            ->where('account_id', $account->username)
            ->latest('id')
            ->get()
            ->filter(fn (WebStorageItem $item): bool => $this->isJewel($item))
            ->values();

        return view('account.jewel-bank', [
            'characters' => $characters,
            'selectedCharacter' => $selectedCharacter,
            'warehouseJewels' => $warehouseJewels,
            'storedJewels' => $storedJewels,
            'jewelCounts' => $this->jewelCounts($storedJewels),
            'jewelDefinitions' => $this->jewelCatalog->definitions(),
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Moves one supported physical jewel into recoverable web custody.
     */
    public function store(StoreJewelRequest $request, StoreJewel $storeJewel): RedirectResponse
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $item = $storeJewel->execute(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                vault: $request->integer('vault'),
                slot: $request->integer('slot'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (WebStorageRejected $exception) {
            return back()->withErrors([
                'jewelBank' => __('ui.web_storage.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = $item->status === WebStorageItemStatus::Available
            ? 'ui.jewel_bank.stored'
            : 'ui.jewel_bank.store_pending';

        return back()->with('jewelBankStatus', __($messageKey));
    }

    /**
     * Returns one owned physical jewel to the account warehouse.
     */
    public function destroy(
        Request $request,
        WebStorageItem $webStorageItem,
        WithdrawWebStorageItem $withdrawWebStorageItem,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        if (! $this->isJewel($webStorageItem)) {
            abort(404);
        }

        try {
            $item = $withdrawWebStorageItem->execute(
                accountId: $account->username,
                itemId: (int) $webStorageItem->getKey(),
            );
        } catch (WebStorageRejected $exception) {
            return back()->withErrors([
                'jewelBank' => __('ui.web_storage.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = $item->status === WebStorageItemStatus::Withdrawn
            ? 'ui.jewel_bank.withdrawn'
            : 'ui.jewel_bank.withdraw_pending';

        return back()->with('jewelBankStatus', __($messageKey));
    }

    /**
     * Returns whether one stored exact item belongs to the Jewel Bank catalog.
     */
    private function isJewel(WebStorageItem $item): bool
    {
        $code = $item->item_snapshot['code'] ?? null;

        return is_string($code) && $this->jewelCatalog->acceptsCode($code);
    }

    /**
     * Counts currently available physical jewels by stable catalog code.
     *
     * @param  Collection<int, WebStorageItem>  $storedJewels
     * @return array<string, int>
     */
    private function jewelCounts(Collection $storedJewels): array
    {
        $counts = array_fill_keys(array_keys($this->jewelCatalog->definitions()), 0);

        foreach ($storedJewels as $item) {
            $code = $item->item_snapshot['code'] ?? null;

            if ($item->status === WebStorageItemStatus::Available && is_string($code)) {
                $counts[$code]++;
            }
        }

        return $counts;
    }
}
