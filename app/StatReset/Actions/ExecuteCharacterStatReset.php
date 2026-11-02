<?php

namespace App\StatReset\Actions;

use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterStatResetOperation;
use App\Reset\ResetProfileReader;
use App\StatReset\CharacterStatResetResult;
use App\StatReset\Exceptions\CharacterStatResetRejected;
use App\StatReset\StatResetBlockReason;
use App\Stats\CharacterStats;
use Throwable;

/**
 * Coordinates idempotency, audit persistence and transactional stat reset.
 */
final readonly class ExecuteCharacterStatReset
{
    /**
     * Creates the stat-reset action with its policy and game write boundary.
     */
    public function __construct(
        private ResetProfileReader $resetProfileReader,
        private CharacterStatResetRepository $statResetRepository,
    ) {}

    /**
     * Executes one at-most-once stat reset for an authenticated account.
     */
    public function handle(
        string $accountId,
        string $characterName,
        string $idempotencyKey,
    ): CharacterStatResetResult {
        $this->ensureGameWritesAreEnabled();

        $existingOperation = CharacterStatResetOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOperation !== null) {
            return $this->resultFromExistingOperation(
                operation: $existingOperation,
                accountId: $accountId,
                characterName: $characterName,
            );
        }

        $settings = $this->resetProfileReader->current();
        $operation = CharacterStatResetOperation::query()->create([
            'idempotency_key' => $idempotencyKey,
            'account_id' => $accountId,
            'character_name' => $characterName,
            'status' => CharacterOperationStatus::Pending,
            'rule_snapshot' => [
                'game_version' => (string) config('game.profile.version'),
                'enabled' => $settings->statResetEnabled,
                'required_level' => $settings->statResetRequiredLevel,
                'required_resets' => $settings->statResetRequiredResets,
                'zen_cost' => $settings->statResetZenCost,
                'profile_revision' => $this->resetProfileReader->revision(),
                'refund_policy' => 'allocated_points_above_class_base',
            ],
        ]);

        try {
            $result = $this->statResetRepository->resetStats(
                accountId: $accountId,
                characterName: $characterName,
                settings: $settings,
            );
        } catch (CharacterStatResetRejected $exception) {
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
            throw new CharacterStatResetRejected(
                StatResetBlockReason::WritesDisabled,
                503,
            );
        }
    }

    /**
     * Returns a completed prior result or safely rejects an unfinished duplicate.
     */
    private function resultFromExistingOperation(
        CharacterStatResetOperation $operation,
        string $accountId,
        string $characterName,
    ): CharacterStatResetResult {
        if (
            $operation->account_id !== $accountId
            || $operation->character_name !== $characterName
        ) {
            throw new CharacterStatResetRejected(
                StatResetBlockReason::IdempotencyConflict,
                409,
            );
        }

        if (
            $operation->status !== CharacterOperationStatus::Completed
            || ! is_array($operation->before_snapshot)
            || ! is_array($operation->after_snapshot)
        ) {
            throw new CharacterStatResetRejected(
                StatResetBlockReason::OperationInProgress,
                409,
            );
        }

        $beforeSnapshot = $operation->before_snapshot;
        $afterSnapshot = $operation->after_snapshot;

        return new CharacterStatResetResult(
            characterName: $operation->character_name,
            beforeZen: (int) $beforeSnapshot['zen'],
            afterZen: (int) $afterSnapshot['zen'],
            beforePoints: (int) $beforeSnapshot['points'],
            refundedPoints: (int) $afterSnapshot['refunded_points'],
            afterPoints: (int) $afterSnapshot['points'],
            beforeStats: $this->statsFromSnapshot($beforeSnapshot),
            afterStats: $this->statsFromSnapshot($afterSnapshot),
        );
    }

    /**
     * Reconstructs typed stats from one trusted stat-reset audit snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function statsFromSnapshot(array $snapshot): CharacterStats
    {
        $stats = $snapshot['stats'];

        return new CharacterStats(
            strength: (int) $stats['strength'],
            agility: (int) $stats['agility'],
            vitality: (int) $stats['vitality'],
            energy: (int) $stats['energy'],
        );
    }
}
