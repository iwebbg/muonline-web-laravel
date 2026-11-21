<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use Closure;
use Illuminate\Database\Connection;

/**
 * Commits native item movement and its immutable receipt in the same game transaction.
 */
final readonly class ItemOperationJournalMuDevsSeason21
{
    public const TABLE = 'IWEB_S21_ItemOperation';

    /**
     * Runs one custody mutation once and rejects conflicting reuse of its identity.
     *
     * Empty source cells alone never establish successful extraction. Only a
     * matching committed receipt can acknowledge an earlier extraction retry.
     *
     * @param  Closure(): MarketEscrowResult  $apply
     */
    public function run(
        Connection $connection,
        string $operationKey,
        string $accountId,
        string $direction,
        string $payloadHash,
        MarketEscrowResult $appliedResult,
        Closure $apply,
    ): MarketEscrowResult {
        if (preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/', $operationKey) !== 1) {
            throw new MarketListingRejected(MarketListingBlockReason::IdempotencyConflict);
        }

        return $connection->transaction(function () use ($connection, $operationKey, $accountId, $direction, $payloadHash, $appliedResult, $apply): MarketEscrowResult {
            $connection->table('MEMB_STAT')
                ->where('memb___id', $accountId)
                ->lockForUpdate()
                ->first(['ConnectStat']);
            $receipt = $connection->table(self::TABLE)
                ->where('OperationKey', $operationKey)
                ->where('Direction', $direction)
                ->first(['AccountID as account_id', 'PayloadHash as payload_hash']);

            if ($receipt !== null) {
                if (! hash_equals((string) $receipt->account_id, $accountId)
                    || ! hash_equals((string) $receipt->payload_hash, $payloadHash)) {
                    throw new MarketListingRejected(MarketListingBlockReason::IdempotencyConflict);
                }

                return $appliedResult === MarketEscrowResult::Extracted
                    ? MarketEscrowResult::AlreadyExtracted
                    : MarketEscrowResult::AlreadyDeposited;
            }

            $result = $apply();

            if ($appliedResult === MarketEscrowResult::Extracted && $result !== MarketEscrowResult::Extracted) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
            }

            $connection->table(self::TABLE)->insert([
                'OperationKey' => $operationKey,
                'Direction' => $direction,
                'AccountID' => $accountId,
                'PayloadHash' => $payloadHash,
            ]);

            return $result;
        }, attempts: 3);
    }
}
