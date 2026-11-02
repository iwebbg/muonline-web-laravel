<?php

namespace App\ZenBank\Actions;

use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Models\AccountZenBalance;
use App\Models\ZenBankOperation;
use App\ZenBank\Exceptions\ZenBankRejected;
use App\ZenBank\ZenBankBlockReason;
use App\ZenBank\ZenBankOperationStatus;
use App\ZenBank\ZenBankOperationType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reserves and starts one replay-safe transfer between warehouse and web bank.
 */
final readonly class CreateZenBankOperation
{
    private const MAXIMUM_GAME_ZEN = 2_000_000_000;

    /**
     * Creates the action with ownership, game balance and synchronization boundaries.
     */
    public function __construct(
        private AccountReadRepository $accountReadRepository,
        private ZenBankRepository $zenBankRepository,
        private SynchronizeZenBankOperation $synchronizeZenBankOperation,
    ) {}

    /**
     * Creates one idempotent operation and attempts its game-database projection.
     */
    public function execute(
        string $accountId,
        string $characterName,
        ZenBankOperationType $type,
        int $amount,
        string $idempotencyKey,
    ): ZenBankOperation {
        $requestHash = $this->requestHash($accountId, $characterName, $type, $amount);
        $existingOperation = ZenBankOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOperation instanceof ZenBankOperation) {
            return $this->resolveExisting($existingOperation, $requestHash);
        }

        if (! (bool) config('game.writes_enabled', false)
            || (bool) config('database.connections.game.readonly', true)) {
            throw new ZenBankRejected(ZenBankBlockReason::WritesDisabled);
        }

        if ($amount < 1 || $amount > self::MAXIMUM_GAME_ZEN) {
            throw new ZenBankRejected(ZenBankBlockReason::InvalidAmount);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new ZenBankRejected(ZenBankBlockReason::CharacterNotFound);
        }

        if ($character->online) {
            throw new ZenBankRejected(ZenBankBlockReason::CharacterOnline);
        }

        $gameBalance = $this->zenBankRepository->currentBalance($accountId);

        if ($gameBalance === null) {
            throw new ZenBankRejected(ZenBankBlockReason::WarehouseNotFound);
        }

        if ($type === ZenBankOperationType::Deposit && $gameBalance < $amount) {
            throw new ZenBankRejected(ZenBankBlockReason::InsufficientGameZen);
        }

        if ($type === ZenBankOperationType::Withdrawal
            && $gameBalance + $amount > self::MAXIMUM_GAME_ZEN) {
            throw new ZenBankRejected(ZenBankBlockReason::InvalidAmount);
        }

        try {
            $operation = DB::transaction(function () use (
                $accountId,
                $characterName,
                $type,
                $amount,
                $idempotencyKey,
                $requestHash,
                $gameBalance,
            ): ZenBankOperation {
                $balanceId = AccountZenBalance::query()->firstOrCreate(
                    ['account_id' => $accountId],
                    ['balance' => 0],
                )->getKey();
                $balance = AccountZenBalance::query()
                    ->whereKey($balanceId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $hasPendingOperation = ZenBankOperation::query()
                    ->where('account_id', $accountId)
                    ->where('status', ZenBankOperationStatus::Pending->value)
                    ->exists();

                if ($hasPendingOperation) {
                    throw new ZenBankRejected(ZenBankBlockReason::PendingOperation);
                }

                $beforeBankBalance = $balance->balance;
                $afterBankBalance = $type === ZenBankOperationType::Deposit
                    ? $beforeBankBalance + $amount
                    : $beforeBankBalance - $amount;

                if ($afterBankBalance < 0) {
                    throw new ZenBankRejected(ZenBankBlockReason::InsufficientBankZen);
                }

                if ($type === ZenBankOperationType::Withdrawal) {
                    $balance->update(['balance' => $afterBankBalance]);
                }

                return ZenBankOperation::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'account_id' => $accountId,
                    'character_name' => $characterName,
                    'type' => $type,
                    'amount' => $amount,
                    'before_game_zen' => $gameBalance,
                    'after_game_zen' => $type === ZenBankOperationType::Deposit
                        ? $gameBalance - $amount
                        : $gameBalance + $amount,
                    'before_bank_balance' => $beforeBankBalance,
                    'after_bank_balance' => $afterBankBalance,
                    'status' => ZenBankOperationStatus::Pending,
                    'sync_attempts' => 0,
                    'account_zen_balance_id' => $balance->getKey(),
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingOperation = ZenBankOperation::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOperation instanceof ZenBankOperation) {
                return $this->resolveExisting($existingOperation, $requestHash);
            }

            throw $exception;
        }

        $this->synchronizeZenBankOperation->execute($operation);

        return $operation->refresh();
    }

    /**
     * Returns an identical replay and retries pending projection when needed.
     */
    private function resolveExisting(
        ZenBankOperation $operation,
        string $requestHash,
    ): ZenBankOperation {
        if (! hash_equals($operation->request_hash, $requestHash)) {
            throw new ZenBankRejected(ZenBankBlockReason::IdempotencyConflict);
        }

        if ($operation->status === ZenBankOperationStatus::Pending) {
            $this->synchronizeZenBankOperation->execute($operation);
        }

        return $operation->refresh();
    }

    /**
     * Produces the deterministic fingerprint bound to one transfer request.
     */
    private function requestHash(
        string $accountId,
        string $characterName,
        ZenBankOperationType $type,
        int $amount,
    ): string {
        $payload = json_encode([
            'account_id' => $accountId,
            'character_name' => $characterName,
            'type' => $type->value,
            'amount' => $amount,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
