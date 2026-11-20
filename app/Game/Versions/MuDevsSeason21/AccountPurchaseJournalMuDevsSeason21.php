<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Economy\Exceptions\NativePurchaseRejected;
use App\Economy\NativePurchaseBlockReason;
use App\Economy\NativePurchaseResult;
use Closure;
use Illuminate\Database\Connection;

/**
 * Commits a native account grant or definitive rejection with its immutable payment receipt.
 */
final readonly class AccountPurchaseJournalMuDevsSeason21
{
    public const TABLE = 'IWEB_S21_AccountPurchase';

    /**
     * Locks the account before applying or acknowledging one exact paid operation.
     *
     * A savepoint rolls back a rejected callback before its rejection is recorded.
     * Throwing that rejection after the outer commit prevents a concurrent late
     * retry from granting a purchase that has already been refunded.
     *
     * @param  Closure(): NativePurchaseResult  $apply
     */
    public function run(
        Connection $connection,
        string $key,
        string $accountId,
        string $payloadHash,
        Closure $apply,
    ): NativePurchaseResult {
        if (preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/', $key) !== 1) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::IdempotencyConflict);
        }

        $outcome = $connection->transaction(function () use ($connection, $key, $accountId, $payloadHash, $apply): NativePurchaseResult|NativePurchaseRejected {
            $connection->table('MEMB_STAT')->where('memb___id', $accountId)->lockForUpdate()->first(['ConnectStat']);
            $receipt = $connection->table(self::TABLE)->where('OperationKey', $key)->first();

            if ($receipt !== null) {
                if (! hash_equals((string) $receipt->AccountID, $accountId)
                    || ! hash_equals((string) $receipt->PayloadHash, $payloadHash)) {
                    throw new NativePurchaseRejected(NativePurchaseBlockReason::IdempotencyConflict);
                }

                $result = json_decode((string) $receipt->ResultJson, true, flags: JSON_THROW_ON_ERROR);
                if (isset($result['rejection'])) {
                    return new NativePurchaseRejected(NativePurchaseBlockReason::from($result['rejection']));
                }

                return new NativePurchaseResult($result['before'], $result['after']);
            }

            try {
                $result = $connection->transaction($apply);
                $payload = ['before' => $result->before, 'after' => $result->after];
            } catch (NativePurchaseRejected $rejection) {
                $result = $rejection;
                $payload = ['rejection' => $rejection->reason->value];
            }

            $connection->table(self::TABLE)->insert([
                'OperationKey' => $key,
                'AccountID' => $accountId,
                'PayloadHash' => $payloadHash,
                'ResultJson' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            return $result;
        }, attempts: 3);

        if ($outcome instanceof NativePurchaseRejected) {
            throw $outcome;
        }

        return $outcome;
    }
}
