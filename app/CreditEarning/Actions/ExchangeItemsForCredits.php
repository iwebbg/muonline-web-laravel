<?php

namespace App\CreditEarning\Actions;

use App\CreditEarning\CreditEarningBlockReason;
use App\CreditEarning\CreditExchangeStatus;
use App\CreditEarning\Exceptions\CreditEarningRejected;
use App\CreditEarning\ItemExchangeMatcher;
use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\ExpectedInventoryItem;
use App\Game\Data\ItemSlot;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Models\CreditExchangeOffer;
use App\Models\CreditItemExchange;
use App\Shop\EconomySettingReader;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Exchanges an exact batch of offline character items for an idempotent Credits award.
 */
final readonly class ExchangeItemsForCredits
{
    /**
     * Creates the action with game reads, guarded extraction and wallet boundaries.
     */
    public function __construct(
        private EconomySettingReader $economySettingReader,
        private AccountReadRepository $accountReadRepository,
        private ItemStorageRepository $itemStorageRepository,
        private CharacterInventoryEscrowRepository $inventoryEscrowRepository,
        private ItemExchangeMatcher $matcher,
        private AdjustWalletBalance $adjustWalletBalance,
        private DatabaseManager $database,
    ) {}

    /**
     * Creates or resumes one exact item exchange using its immutable request key.
     */
    public function execute(
        string $accountId,
        string $characterName,
        string $offerPublicId,
        int $quantity,
        string $idempotencyKey,
    ): CreditItemExchange {
        $requestHash = $this->requestHash(
            $accountId,
            $characterName,
            $offerPublicId,
            $quantity,
        );
        $existing = CreditItemExchange::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof CreditItemExchange) {
            return $this->perform($this->resolveExisting($existing, $requestHash));
        }

        $settings = $this->economySettingReader->current();

        if (! $settings->itemExchangeEnabled) {
            throw new CreditEarningRejected(CreditEarningBlockReason::ServiceDisabled);
        }

        if (! config('game.writes_enabled', false) || config('database.connections.game.readonly', true)) {
            throw new CreditEarningRejected(CreditEarningBlockReason::WritesDisabled, 503);
        }

        $offer = CreditExchangeOffer::query()
            ->where('public_id', $offerPublicId)
            ->where('game_profile_key', (string) config('game.profile.key'))
            ->where('enabled', true)
            ->whereNull('archived_at')
            ->first();

        if (! $offer instanceof CreditExchangeOffer) {
            throw new CreditEarningRejected(CreditEarningBlockReason::OfferNotFound, 404);
        }

        if ($quantity < 1 || $quantity > $offer->max_quantity_per_exchange) {
            throw new CreditEarningRejected(CreditEarningBlockReason::InvalidQuantity);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new CreditEarningRejected(CreditEarningBlockReason::CharacterNotFound, 404);
        }

        if ($character->online) {
            throw new CreditEarningRejected(CreditEarningBlockReason::CharacterOnline);
        }

        $storage = $this->itemStorageRepository->forAccountAndCharacter($accountId, $characterName);
        $matchingItems = $storage === null
            ? []
            : array_values(array_filter(
                $storage->character->inventory,
                fn (ItemSlot $item): bool => $this->matcher->matches($item, $offer),
            ));

        if (count($matchingItems) < $quantity) {
            throw new CreditEarningRejected(CreditEarningBlockReason::ItemsUnavailable);
        }

        $selectedItems = array_slice($matchingItems, 0, $quantity);
        $creditsAwarded = $offer->credits_per_item * $quantity;
        try {
            $operation = $this->database->transaction(function () use (
                $accountId,
                $characterName,
                $creditsAwarded,
                $idempotencyKey,
                $offer,
                $quantity,
                $requestHash,
                $selectedItems,
            ): CreditItemExchange {
                $lockedOffer = CreditExchangeOffer::query()->lockForUpdate()->findOrFail($offer->getKey());

                if (
                    $lockedOffer->revision !== $offer->revision
                    || ! $lockedOffer->enabled
                    || $lockedOffer->archived_at !== null
                ) {
                    throw new CreditEarningRejected(CreditEarningBlockReason::OfferNotFound, 409);
                }

                $awardedToday = CreditItemExchange::query()
                    ->where('account_id', $accountId)
                    ->where('credit_exchange_offer_id', $lockedOffer->getKey())
                    ->whereDate('created_at', today())
                    ->whereIn('status', [
                        CreditExchangeStatus::Pending,
                        CreditExchangeStatus::Extracted,
                        CreditExchangeStatus::Completed,
                    ])
                    ->sum('credits_awarded');

                if ($awardedToday + $creditsAwarded > $lockedOffer->daily_account_credit_limit) {
                    throw new CreditEarningRejected(CreditEarningBlockReason::DailyLimitReached);
                }

                return CreditItemExchange::query()->create([
                    'public_id' => (string) Uuid::uuid4(),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'account_id' => $accountId,
                    'character_name' => $characterName,
                    'credit_exchange_offer_id' => $lockedOffer->getKey(),
                    'quantity' => $quantity,
                    'credits_awarded' => $creditsAwarded,
                    'offer_snapshot' => $lockedOffer->snapshot(),
                    'items_snapshot' => array_map(
                        static fn (ItemSlot $item): array => [
                            ...$item->toArray(),
                            'binary_hex' => bin2hex($item->binary()),
                        ],
                        $selectedItems,
                    ),
                    'status' => CreditExchangeStatus::Pending,
                ]);
            });
        } catch (QueryException $exception) {
            $existing = CreditItemExchange::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof CreditItemExchange) {
                return $this->perform($this->resolveExisting($existing, $requestHash));
            }

            throw $exception;
        }

        return $this->perform($operation);
    }

    /**
     * Resumes one stored exchange after a crash or transient wallet failure.
     */
    public function perform(CreditItemExchange $operation): CreditItemExchange
    {
        if ($operation->status === CreditExchangeStatus::Completed) {
            return $operation;
        }

        if ($operation->status === CreditExchangeStatus::Failed) {
            throw new CreditEarningRejected(
                CreditEarningBlockReason::tryFrom((string) $operation->failure_code)
                    ?? CreditEarningBlockReason::ItemChanged,
            );
        }

        if ($operation->status === CreditExchangeStatus::Pending) {
            $expectedItems = array_map(
                static fn (array $item): ExpectedInventoryItem => new ExpectedInventoryItem(
                    slot: (int) $item['slot'],
                    binary: (string) hex2bin((string) $item['binary_hex']),
                ),
                $operation->items_snapshot,
            );

            try {
                $this->inventoryEscrowRepository->extractMany(
                    $operation->account_id,
                    $operation->character_name,
                    $expectedItems,
                );
                $operation->update(['status' => CreditExchangeStatus::Extracted]);
            } catch (MarketListingRejected $exception) {
                $reason = $this->mapGameRejection($exception->reason);
                $operation->update([
                    'status' => CreditExchangeStatus::Failed,
                    'failure_code' => $reason->value,
                    'completed_at' => now(),
                ]);

                throw new CreditEarningRejected($reason);
            }
        }

        try {
            $walletTransaction = $this->adjustWalletBalance->execute(
                accountId: $operation->account_id,
                currency: WalletCurrency::Credits,
                type: WalletTransactionType::Reward,
                amount: $operation->credits_awarded,
                reason: 'Item exchange: '.(string) $operation->offer_snapshot['item_name'],
                administratorId: null,
                idempotencyKey: $this->walletKey($operation->idempotency_key),
            );
        } catch (Throwable $exception) {
            report($exception);

            throw $exception;
        }

        $operation->update([
            'status' => CreditExchangeStatus::Completed,
            'wallet_transaction_id' => $walletTransaction->getKey(),
            'failure_code' => null,
            'completed_at' => now(),
        ]);

        return $operation->refresh();
    }

    /**
     * Resolves an identical replay or rejects key reuse with different input.
     */
    private function resolveExisting(CreditItemExchange $operation, string $requestHash): CreditItemExchange
    {
        if (! hash_equals($operation->request_hash, $requestHash)) {
            throw new CreditEarningRejected(CreditEarningBlockReason::IdempotencyConflict, 409);
        }

        return $operation;
    }

    /**
     * Maps shared inventory write failures to Credits earning reasons.
     */
    private function mapGameRejection(MarketListingBlockReason $reason): CreditEarningBlockReason
    {
        return match ($reason) {
            MarketListingBlockReason::WritesDisabled => CreditEarningBlockReason::WritesDisabled,
            MarketListingBlockReason::CharacterNotFound => CreditEarningBlockReason::CharacterNotFound,
            MarketListingBlockReason::CharacterOnline => CreditEarningBlockReason::CharacterOnline,
            MarketListingBlockReason::ItemChanged => CreditEarningBlockReason::ItemChanged,
            default => CreditEarningBlockReason::ItemsUnavailable,
        };
    }

    /**
     * Produces the request fingerprint independent of mutable exchange pricing.
     */
    private function requestHash(
        string $accountId,
        string $characterName,
        string $offerPublicId,
        int $quantity,
    ): string {
        return hash('sha256', json_encode([
            'account_id' => $accountId,
            'character_name' => $characterName,
            'offer_public_id' => $offerPublicId,
            'quantity' => $quantity,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Derives the deterministic wallet key owned by one exchange operation.
     */
    private function walletKey(string $idempotencyKey): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'muonline-credit-item-exchange:'.$idempotencyKey,
        )->toString();
    }
}
