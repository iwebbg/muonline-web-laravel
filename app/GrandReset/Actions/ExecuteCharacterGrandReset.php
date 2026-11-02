<?php

namespace App\GrandReset\Actions;

use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\GrandReset\CharacterGrandResetResult;
use App\GrandReset\Exceptions\CharacterGrandResetRejected;
use App\GrandReset\GrandResetBlockReason;
use App\Models\CharacterGrandResetOperation;
use App\Reset\ResetProfileReader;
use App\Stats\CharacterStats;
use Throwable;

/**
 * Coordinates idempotency, audit persistence and transactional grand reset.
 */
final readonly class ExecuteCharacterGrandReset
{
    /**
     * Creates the grand-reset action with its policy and game write boundary.
     */
    public function __construct(
        private ResetProfileReader $resetProfileReader,
        private CharacterGrandResetRepository $grandResetRepository,
    ) {}

    /**
     * Executes one at-most-once grand reset for an authenticated account.
     */
    public function handle(
        string $accountId,
        string $characterName,
        string $idempotencyKey,
    ): CharacterGrandResetResult {
        $this->ensureGameWritesAreEnabled();

        $existingOperation = CharacterGrandResetOperation::query()
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
        $settingsSnapshot = [
            ...$settings->toPersistenceArray(),
            'revision' => $this->resetProfileReader->revision(),
        ];
        $operation = CharacterGrandResetOperation::query()->create([
            'idempotency_key' => $idempotencyKey,
            'account_id' => $accountId,
            'character_name' => $characterName,
            'status' => CharacterOperationStatus::Pending,
            'settings_snapshot' => $settingsSnapshot,
        ]);

        try {
            $result = $this->grandResetRepository->grandReset(
                accountId: $accountId,
                characterName: $characterName,
                settings: $settings,
            );
        } catch (CharacterGrandResetRejected $exception) {
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
            throw new CharacterGrandResetRejected(
                GrandResetBlockReason::WritesDisabled,
                503,
            );
        }
    }

    /**
     * Returns a completed prior result or safely rejects an unfinished duplicate.
     */
    private function resultFromExistingOperation(
        CharacterGrandResetOperation $operation,
        string $accountId,
        string $characterName,
    ): CharacterGrandResetResult {
        if (
            $operation->account_id !== $accountId
            || $operation->character_name !== $characterName
        ) {
            throw new CharacterGrandResetRejected(
                GrandResetBlockReason::IdempotencyConflict,
                409,
            );
        }

        if (
            $operation->status !== CharacterOperationStatus::Completed
            || ! is_array($operation->before_snapshot)
            || ! is_array($operation->after_snapshot)
        ) {
            throw new CharacterGrandResetRejected(
                GrandResetBlockReason::OperationInProgress,
                409,
            );
        }

        $beforeSnapshot = $operation->before_snapshot;
        $afterSnapshot = $operation->after_snapshot;

        return new CharacterGrandResetResult(
            characterName: $operation->character_name,
            beforeLevel: (int) $beforeSnapshot['level'],
            afterLevel: (int) $afterSnapshot['level'],
            beforeResets: (int) $beforeSnapshot['resets'],
            afterResets: (int) $afterSnapshot['resets'],
            beforeGrandResets: (int) $beforeSnapshot['grand_resets'],
            afterGrandResets: (int) $afterSnapshot['grand_resets'],
            beforeZen: (int) $beforeSnapshot['zen'],
            afterZen: (int) $afterSnapshot['zen'],
            beforePoints: (int) $beforeSnapshot['points'],
            afterPoints: (int) $afterSnapshot['points'],
            beforeStats: $this->statsFromSnapshot($beforeSnapshot),
            afterStats: $this->statsFromSnapshot($afterSnapshot),
        );
    }

    /**
     * Reconstructs typed stats from one trusted grand-reset audit snapshot.
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
