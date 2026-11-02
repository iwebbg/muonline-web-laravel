<?php

namespace App\Market\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\AuctionBid;
use App\Models\MarketListing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Places one idempotent auction bid while atomically rotating Credits holds.
 */
final readonly class PlaceAuctionBid
{
    /**
     * Creates the bid action with the immutable wallet boundary.
     */
    public function __construct(
        private AdjustWalletBalance $adjustWalletBalance,
    ) {}

    /**
     * Debits the new leader and releases the previous leader in one transaction.
     *
     * @throws MarketListingRejected When bidding, balance or replay checks fail.
     */
    public function execute(
        string $bidderAccountId,
        int $listingId,
        int $amountCredits,
        string $idempotencyKey,
    ): AuctionBid {
        $requestHash = $this->requestHash($bidderAccountId, $listingId, $amountCredits);
        $existingBid = AuctionBid::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingBid instanceof AuctionBid) {
            return $this->resolveExisting($existingBid, $requestHash);
        }

        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new MarketListingRejected(MarketListingBlockReason::WritesDisabled);
        }

        try {
            return DB::transaction(function () use (
                $bidderAccountId,
                $listingId,
                $amountCredits,
                $idempotencyKey,
                $requestHash,
            ): AuctionBid {
                $listing = MarketListing::query()
                    ->whereKey($listingId)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $listing instanceof MarketListing
                    || $listing->sale_type !== MarketSaleType::Auction
                    || $listing->status !== MarketListingStatus::Active
                    || $listing->expires_at === null
                    || $listing->expires_at->isPast()
                ) {
                    throw new MarketListingRejected(MarketListingBlockReason::AuctionUnavailable);
                }

                if ($listing->seller_account_id === $bidderAccountId) {
                    throw new MarketListingRejected(MarketListingBlockReason::OwnListing);
                }

                $minimumBid = $listing->highest_bid_credits === null
                    ? (int) $listing->minimum_bid_credits
                    : $listing->highest_bid_credits + (int) $listing->bid_increment_credits;

                if ($amountCredits < $minimumBid) {
                    throw new MarketListingRejected(MarketListingBlockReason::BidTooLow);
                }

                try {
                    $holdTransaction = $this->adjustWalletBalance->execute(
                        accountId: $bidderAccountId,
                        currency: WalletCurrency::Credits,
                        type: WalletTransactionType::AuctionHold,
                        amount: -$amountCredits,
                        reason: 'Auction bid hold: '.$listing->public_id,
                        administratorId: null,
                        idempotencyKey: $this->walletKey($idempotencyKey, 'hold'),
                    );
                    $previousBid = $listing->highest_bid_id === null
                        ? null
                        : AuctionBid::query()->lockForUpdate()->find($listing->highest_bid_id);

                    if ($previousBid instanceof AuctionBid) {
                        $releaseTransaction = $this->adjustWalletBalance->execute(
                            accountId: $previousBid->bidder_account_id,
                            currency: WalletCurrency::Credits,
                            type: WalletTransactionType::AuctionRelease,
                            amount: $previousBid->amount_credits,
                            reason: 'Outbid auction release: '.$listing->public_id,
                            administratorId: null,
                            idempotencyKey: $this->walletKey($idempotencyKey, 'release'),
                        );
                        $previousBid->update([
                            'release_wallet_transaction_id' => $releaseTransaction->getKey(),
                            'outbid_at' => now(),
                        ]);
                    }
                } catch (WalletAdjustmentRejected $exception) {
                    $this->rethrowWalletRejection($exception);
                }

                $bid = AuctionBid::query()->create([
                    'market_listing_id' => $listing->getKey(),
                    'bidder_account_id' => $bidderAccountId,
                    'amount_credits' => $amountCredits,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'hold_wallet_transaction_id' => $holdTransaction->getKey(),
                ]);
                $listing->update([
                    'highest_bid_credits' => $amountCredits,
                    'highest_bidder_account_id' => $bidderAccountId,
                    'highest_bid_id' => $bid->getKey(),
                ]);

                return $bid;
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingBid = AuctionBid::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingBid instanceof AuctionBid) {
                return $this->resolveExisting($existingBid, $requestHash);
            }

            throw $exception;
        }
    }

    /**
     * Returns an identical bid replay or rejects key reuse for different data.
     */
    private function resolveExisting(AuctionBid $bid, string $requestHash): AuctionBid
    {
        if (! hash_equals($bid->request_hash, $requestHash)) {
            throw new MarketListingRejected(MarketListingBlockReason::IdempotencyConflict);
        }

        return $bid;
    }

    /**
     * Maps wallet failures to public auction rejection reasons.
     */
    private function rethrowWalletRejection(WalletAdjustmentRejected $exception): never
    {
        $reason = match ($exception->reason) {
            WalletAdjustmentBlockReason::InsufficientBalance => MarketListingBlockReason::InsufficientCredits,
            WalletAdjustmentBlockReason::IdempotencyConflict => MarketListingBlockReason::IdempotencyConflict,
            default => MarketListingBlockReason::AuctionUnavailable,
        };

        throw new MarketListingRejected($reason);
    }

    /**
     * Derives stable wallet UUIDs from one public bid replay key.
     */
    private function walletKey(string $idempotencyKey, string $purpose): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'muonline-auction:'.$idempotencyKey.':'.$purpose,
        )->toString();
    }

    /**
     * Produces the deterministic request fingerprint for one bid.
     */
    private function requestHash(string $bidderAccountId, int $listingId, int $amountCredits): string
    {
        $payload = json_encode([
            'bidder_account_id' => $bidderAccountId,
            'listing_id' => $listingId,
            'amount_credits' => $amountCredits,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
