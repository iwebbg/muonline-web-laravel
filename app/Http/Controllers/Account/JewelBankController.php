<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreJewelRequest;
use App\JewelBank\Actions\StoreJewelBatch;
use App\JewelBank\JewelCatalog;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\Eloquent\Builder;
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
     * Displays physical jewel counts and items from one selected game storage.
     */
    public function index(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $characters = $this->accountReadRepository->charactersFor($account->username);
        $sourceLocation = ItemStorageLocation::tryFrom(
            $request->string('source')->toString(),
        ) ?? ItemStorageLocation::Warehouse;
        $requestedCharacter = $request->string('character')->toString();
        $selectedCharacter = null;

        foreach ($characters as $character) {
            if ($character->name === $requestedCharacter) {
                $selectedCharacter = $character->name;
            }
        }

        $selectedCharacter ??= $characters[0]->name ?? null;
        $storage = $sourceLocation === ItemStorageLocation::Inventory
            && is_string($selectedCharacter)
            ? $this->itemStorageRepository->forAccountAndCharacter(
                $account->username,
                $selectedCharacter,
            )
            : $this->itemStorageRepository->forAccount($account->username);
        $vaults = $storage?->vaults ?? [];
        $requestedVault = $request->integer('vault', -1);
        $selectedVault = null;

        foreach ($vaults as $vault) {
            if ($vault->number === $requestedVault) {
                $selectedVault = $vault;
            }
        }

        if ($selectedVault === null) {
            foreach ($vaults as $vault) {
                if ($vault->active) {
                    $selectedVault = $vault;
                    break;
                }
            }
        }

        $selectedVault ??= $vaults[0] ?? null;
        $eligibleJewelGroups = [];
        $sourceItems = $sourceLocation === ItemStorageLocation::Inventory
            ? ($storage?->character->inventory ?? [])
            : ($selectedVault?->items ?? []);

        foreach ($sourceItems as $item) {
            if ($this->jewelCatalog->accepts($item)) {
                $jewelCode = $item->code();
                $jewelLevel = $item->level ?? 0;
                $jewelGroupKey = $jewelCode.':'.$jewelLevel;

                if (! array_key_exists($jewelGroupKey, $eligibleJewelGroups)) {
                    $eligibleJewelGroups[$jewelGroupKey] = [
                        'item' => $item,
                        'quantity' => 0,
                        'idempotencyKey' => (string) Str::uuid(),
                    ];
                }

                $eligibleJewelGroups[$jewelGroupKey]['quantity']++;
            }
        }

        $availableJewels = $this->jewelItemsQuery($account->username)
            ->where('status', WebStorageItemStatus::Available->value)
            ->oldest('id')
            ->get();
        $jewelHistory = $this->jewelItemsQuery($account->username)
            ->latest('id')
            ->paginate(20, ['*'], 'jewel_history')
            ->withQueryString();

        return view('account.jewel-bank', [
            'characters' => $characters,
            'selectedCharacter' => $selectedCharacter,
            'sourceLocation' => $sourceLocation,
            'vaults' => $vaults,
            'selectedVault' => $selectedVault,
            'eligibleJewelGroups' => array_values($eligibleJewelGroups),
            'storedJewelGroups' => $this->groupStoredJewels($availableJewels),
            'jewelHistory' => $jewelHistory,
            'jewelCounts' => $this->jewelCounts($availableJewels),
            'jewelDefinitions' => $this->jewelCatalog->definitions(),
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Moves one supported physical jewel into recoverable web custody.
     */
    public function store(
        StoreJewelRequest $request,
        StoreJewelBatch $storeJewelBatch,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $storedItems = $storeJewelBatch->execute(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                vault: $request->integer('vault'),
                sourceLocation: ItemStorageLocation::from(
                    $request->string('source_location')->toString(),
                ),
                jewelCode: $request->string('jewel_code')->toString(),
                jewelLevel: $request->integer('jewel_level'),
                quantity: $request->integer('quantity'),
                batchIdempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (WebStorageRejected $exception) {
            return back()->withErrors([
                'jewelBank' => __('ui.web_storage.errors.'.$exception->reason->value),
            ]);
        }

        $allItemsAvailable = collect($storedItems)->every(
            static fn (WebStorageItem $item): bool => $item->status === WebStorageItemStatus::Available,
        );
        $messageKey = $allItemsAvailable
            ? 'ui.jewel_bank.stored'
            : 'ui.jewel_bank.store_pending';

        return back()->with('jewelBankStatus', __($messageKey, [
            'quantity' => count($storedItems),
        ]));
    }

    /**
     * Returns one owned physical jewel to the account Main Vault.
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
     * Builds an account-scoped query containing only supported Jewel Bank items.
     *
     * @return Builder<WebStorageItem>
     */
    private function jewelItemsQuery(string $accountId): Builder
    {
        $jewelCodes = array_keys($this->jewelCatalog->definitions());

        return WebStorageItem::query()
            ->where('account_id', $accountId)
            ->where(function (Builder $query) use ($jewelCodes): void {
                foreach ($jewelCodes as $jewelCode) {
                    $query->orWhere('item_snapshot->code', $jewelCode);
                }
            });
    }

    /**
     * Groups currently available custody records by exact jewel type and level.
     *
     * @param  Collection<int, WebStorageItem>  $availableJewels
     * @return list<array{item: WebStorageItem, quantity: int}>
     */
    private function groupStoredJewels(Collection $availableJewels): array
    {
        $groups = [];

        foreach ($availableJewels as $item) {
            $snapshot = $item->item_snapshot;
            $jewelCode = (string) ($snapshot['code'] ?? '');
            $jewelLevel = (int) ($snapshot['level'] ?? 0);
            $groupKey = $jewelCode.':'.$jewelLevel;

            if (! array_key_exists($groupKey, $groups)) {
                $groups[$groupKey] = [
                    'item' => $item,
                    'quantity' => 0,
                ];
            }

            $groups[$groupKey]['quantity']++;
        }

        return array_values($groups);
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
