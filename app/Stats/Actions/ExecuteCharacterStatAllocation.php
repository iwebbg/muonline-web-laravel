<?php

namespace App\Stats\Actions;

use App\Game\Contracts\CharacterStatAllocationRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterStatAllocationOperation;
use App\Reset\ResetProfileReader;
use App\Stats\CharacterStatAllocationResult;
use App\Stats\CharacterStats;
use App\Stats\Exceptions\CharacterStatAllocationRejected;
use App\Stats\StatAllocation;
use App\Stats\StatAllocationBlockReason;
use Throwable;

/**
 * Coordinates idempotency, audit persistence and transactional stat allocation.
 */
final readonly class ExecuteCharacterStatAllocation
{
    /**
     * Creates the allocation action with its game write boundary.
     */
    public function __construct(
        private CharacterStatAllocationRepository $statAllocationRepository,
        private ResetProfileReader $resetProfileReader,
    ) {}

    /**
     * Executes one at-most-once allocation for an authenticated account.
     */
    public function handle(
        string $accountId,
        string $characterName,
        string $idempotencyKey,
        StatAllocation $allocation,
    ): CharacterStatAllocationResult {
        $this->ensureGameWritesAreEnabled();
        $settings = $this->resetProfileReader->current();
        $revision = $this->resetProfileReader->revision();

        $existingOperation = CharacterStatAllocationOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOperation !== null) {
            return $this->resultFromExistingOperation(
                operation: $existingOperation,
                accountId: $accountId,
                characterName: $characterName,
                allocation: $allocation,
            );
        }

        $operation = CharacterStatAllocationOperation::query()->create([
            'idempotency_key' => $idempotencyKey,
            'account_id' => $accountId,
            'character_name' => $characterName,
            'status' => CharacterOperationStatus::Pending,
            'allocation_snapshot' => $allocation->toArray(),
            'rule_snapshot' => [
                'game_version' => (string) config('game.profile.version'),
                'maximum_stat' => $settings->maximumStat,
                'technical_maximum_stat' => 32_767,
                'profile_revision' => $revision,
                'source' => 'web_admin',
            ],
        ]);

        try {
            $result = $this->statAllocationRepository->allocate(
                accountId: $accountId,
                characterName: $characterName,
                allocation: $allocation,
                settings: $settings,
            );
        } catch (CharacterStatAllocationRejected $exception) {
            $operation->update([
                'status' => CharacterOperationStatus::Failed,
                'failure_code' => $exception->reason->value,
                'completed_at' => now(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $operation->update([
                'status' => CharacterOperationStatus::Failed,
                'failure_code' => 'unexpected_error',
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $operation->update([
            'status' => CharacterOperationStatus::Completed,
            'before_snapshot' => $result->beforeSnapshot(),
            'after_snapshot' => $result->afterSnapshot(),
            'completed_at' => now(),
        ]);

        return $result;
    }

    /**
     * Rejects mutations unless both explicit write flags permit game changes.
     */
    private function ensureGameWritesAreEnabled(): void
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new CharacterStatAllocationRejected(
                StatAllocationBlockReason::WritesDisabled,
                503,
            );
        }
    }

    /**
     * Returns a completed prior result or rejects an unsafe duplicate request.
     */
    private function resultFromExistingOperation(
        CharacterStatAllocationOperation $operation,
        string $accountId,
        string $characterName,
        StatAllocation $allocation,
    ): CharacterStatAllocationResult {
        if (
            $operation->account_id !== $accountId
            || $operation->character_name !== $characterName
            || $operation->allocation_snapshot !== $allocation->toArray()
        ) {
            throw new CharacterStatAllocationRejected(
                StatAllocationBlockReason::IdempotencyConflict,
                409,
            );
        }

        if (
            $operation->status !== CharacterOperationStatus::Completed
            || ! is_array($operation->before_snapshot)
            || ! is_array($operation->after_snapshot)
        ) {
            throw new CharacterStatAllocationRejected(
                StatAllocationBlockReason::OperationInProgress,
                409,
            );
        }

        $beforeSnapshot = $operation->before_snapshot;
        $afterSnapshot = $operation->after_snapshot;

        return new CharacterStatAllocationResult(
            characterName: $operation->character_name,
            beforeStats: $this->statsFromSnapshot($beforeSnapshot),
            afterStats: $this->statsFromSnapshot($afterSnapshot),
            beforePoints: (int) $beforeSnapshot['points'],
            afterPoints: (int) $afterSnapshot['points'],
            allocation: $allocation,
        );
    }

    /**
     * Reconstructs typed character stats from one trusted audit snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function statsFromSnapshot(array $snapshot): CharacterStats
    {
        $stats = $snapshot['stats'];

        return CharacterStats::fromArray(array_map(
            static fn (mixed $value): int => (int) $value,
            $stats,
        ));
    }
}
