<?php

namespace App\ZenBank\Actions;

use App\Game\Contracts\ZenBankRepository;
use App\Models\AccountZenBalance;
use App\Models\ZenBankOperation;
use App\ZenBank\Exceptions\ZenBankRejected;
use App\ZenBank\ZenBankOperationStatus;
use App\ZenBank\ZenBankOperationType;
use App\ZenBank\ZenProjectionResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Projects and finalizes one recoverable Zen Bank transfer.
 */
final readonly class SynchronizeZenBankOperation
{
    /**
     * Creates the synchronizer with its compare-and-set game writer.
     */
    public function __construct(private ZenBankRepository $zenBankRepository) {}

    /**
     * Attempts the game projection and atomically finalizes the web ledger.
     */
    public function execute(ZenBankOperation $operation): bool
    {
        if ($operation->status !== ZenBankOperationStatus::Pending) {
            return $operation->status === ZenBankOperationStatus::Completed;
        }

        if (! (bool) config('game.writes_enabled', false)
            || (bool) config('database.connections.game.readonly', true)) {
            $this->recordFailure($operation, 'writes_disabled');

            return false;
        }

        try {
            $projection = $this->zenBankRepository->project(
                accountId: $operation->account_id,
                expectedBalance: $operation->before_game_zen,
                resultingBalance: $operation->after_game_zen,
            );

            if ($projection === ZenProjectionResult::Conflict) {
                $this->recordProjectionConflict($operation);

                return false;
            }

            DB::transaction(function () use ($operation): void {
                $lockedOperation = ZenBankOperation::query()
                    ->whereKey($operation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedOperation->status !== ZenBankOperationStatus::Pending) {
                    return;
                }

                $balance = AccountZenBalance::query()
                    ->whereKey($lockedOperation->account_zen_balance_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedOperation->type === ZenBankOperationType::Deposit) {
                    if ($balance->balance !== $lockedOperation->before_bank_balance) {
                        $lockedOperation->forceFill([
                            'status' => ZenBankOperationStatus::Conflict,
                            'last_sync_error' => 'bank_balance_conflict',
                        ])->save();

                        return;
                    }

                    $balance->update(['balance' => $lockedOperation->after_bank_balance]);
                }

                $lockedOperation->forceFill([
                    'status' => ZenBankOperationStatus::Completed,
                    'sync_attempts' => $lockedOperation->sync_attempts + 1,
                    'last_sync_attempt_at' => now(),
                    'last_sync_error' => null,
                    'completed_at' => now(),
                ])->save();
            }, attempts: 3);
        } catch (ZenBankRejected $exception) {
            $this->recordFailure($operation, $exception->reason->value);

            return false;
        } catch (Throwable) {
            $this->recordFailure($operation, 'game_sync_failed');

            return false;
        }

        return $operation->refresh()->status === ZenBankOperationStatus::Completed;
    }

    /**
     * Records one retryable synchronization failure without infrastructure details.
     */
    private function recordFailure(ZenBankOperation $operation, string $reason): void
    {
        $operation->forceFill([
            'sync_attempts' => $operation->sync_attempts + 1,
            'last_sync_attempt_at' => now(),
            'last_sync_error' => $reason,
        ])->save();
    }

    /**
     * Marks a compare-and-set conflict and releases any reserved withdrawal funds.
     */
    private function recordProjectionConflict(ZenBankOperation $operation): void
    {
        DB::transaction(function () use ($operation): void {
            $lockedOperation = ZenBankOperation::query()
                ->whereKey($operation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $balance = AccountZenBalance::query()
                ->whereKey($lockedOperation->account_zen_balance_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOperation->type === ZenBankOperationType::Withdrawal
                && $balance->balance === $lockedOperation->after_bank_balance) {
                $balance->update(['balance' => $lockedOperation->before_bank_balance]);
            }

            $lockedOperation->forceFill([
                'status' => ZenBankOperationStatus::Conflict,
                'sync_attempts' => $lockedOperation->sync_attempts + 1,
                'last_sync_attempt_at' => now(),
                'last_sync_error' => 'game_balance_conflict',
            ])->save();
        }, attempts: 3);
    }
}
