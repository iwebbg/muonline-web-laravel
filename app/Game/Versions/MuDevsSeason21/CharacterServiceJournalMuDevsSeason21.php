<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use Closure;
use Illuminate\Database\Connection;

/**
 * Commits native paid progression or a definitive rejection with its immutable receipt.
 */
final readonly class CharacterServiceJournalMuDevsSeason21
{
    public const TABLE = 'IWEB_S21_CharacterOperation';

    /**
     * Locks the account before applying or acknowledging one exact paid operation.
     *
     * A savepoint rolls back a rejected callback before its rejection is recorded.
     * Throwing that rejection after the outer commit prevents a concurrent late
     * retry from granting a purchase that has already been refunded.
     *
     * @param  Closure(): CharacterServiceResult  $apply
     */
    public function run(
        Connection $connection,
        string $key,
        string $accountId,
        string $payloadHash,
        Closure $apply,
    ): CharacterServiceResult {
        if (preg_match('/\A[A-Za-z0-9:_-]{1,128}\z/', $key) !== 1) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::IdempotencyConflict, 409);
        }

        $outcome = $connection->transaction(function () use ($connection, $key, $accountId, $payloadHash, $apply): CharacterServiceResult|CharacterServiceRejected {
            $connection->table('MEMB_STAT')->where('memb___id', $accountId)->lockForUpdate()->first(['ConnectStat']);
            $receipt = $connection->table(self::TABLE)->where('OperationKey', $key)->first();

            if ($receipt !== null) {
                if (! hash_equals((string) $receipt->AccountID, $accountId)
                    || ! hash_equals((string) $receipt->PayloadHash, $payloadHash)) {
                    throw new CharacterServiceRejected(CharacterServiceBlockReason::IdempotencyConflict, 409);
                }

                $result = json_decode((string) $receipt->ResultJson, true, flags: JSON_THROW_ON_ERROR);
                if (isset($result['rejection'])) {
                    return new CharacterServiceRejected(CharacterServiceBlockReason::from($result['rejection']), (int) $result['status']);
                }

                return new CharacterServiceResult($result['before'], $result['after']);
            }

            try {
                $result = $connection->transaction($apply);
                $payload = ['before' => $result->before, 'after' => $result->after];
            } catch (CharacterServiceRejected $rejection) {
                $result = $rejection;
                $payload = ['rejection' => $rejection->reason->value, 'status' => $rejection->status];
            }

            $connection->table(self::TABLE)->insert([
                'OperationKey' => $key,
                'AccountID' => $accountId,
                'PayloadHash' => $payloadHash,
                'ResultJson' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            return $result;
        }, attempts: 3);

        if ($outcome instanceof CharacterServiceRejected) {
            throw $outcome;
        }

        return $outcome;
    }
}
