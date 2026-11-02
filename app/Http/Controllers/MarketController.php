<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Market\MarketListingStatus;
use App\Models\MarketListing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays public market escrow listings and the authenticated seller workspace.
 */
final class MarketController extends Controller
{
    /**
     * Creates the market page with version-aware character and warehouse readers.
     */
    public function __construct(
        private readonly AccountReadRepository $accountReadRepository,
        private readonly ItemStorageRepository $itemStorageRepository,
    ) {}

    /**
     * Displays active listings and only the current account's sellable storage.
     */
    public function __invoke(Request $request): View
    {
        $listings = MarketListing::query()
            ->where('status', MarketListingStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('activated_at')
            ->paginate(24);
        $account = $request->user();
        $characters = [];
        $selectedCharacter = null;
        $warehouseVaults = [];
        $myListings = collect();

        if ($account instanceof GameAccount) {
            $characterEntries = $this->accountReadRepository->charactersFor($account->username);

            foreach ($characterEntries as $characterEntry) {
                $characters[] = $characterEntry->toArray();
            }

            $requestedCharacter = $request->string('character')->toString();
            $selectedCharacter = $this->ownedCharacterName($characters, $requestedCharacter)
                ?? ($characters[0]['name'] ?? null);

            if (is_string($selectedCharacter)) {
                $storage = $this->itemStorageRepository->forAccountAndCharacter(
                    $account->username,
                    $selectedCharacter,
                );
                $warehouseVaults = $storage?->vaults ?? [];
            }

            $myListings = MarketListing::query()
                ->where('seller_account_id', $account->username)
                ->latest('id')
                ->limit(50)
                ->get();
        }

        return view('market.index', [
            'listings' => $listings,
            'characters' => $characters,
            'selectedCharacter' => $selectedCharacter,
            'warehouseVaults' => $warehouseVaults,
            'myListings' => $myListings,
            'listingIdempotencyKey' => (string) Str::uuid(),
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Returns the requested name only when it exists in the owned character list.
     *
     * @param  list<array<string, mixed>>  $characters
     */
    private function ownedCharacterName(array $characters, string $requestedName): ?string
    {
        foreach ($characters as $character) {
            if (($character['name'] ?? null) === $requestedName) {
                return $requestedName;
            }
        }

        return null;
    }
}
