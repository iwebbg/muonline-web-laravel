<?php

namespace App\Reset\Actions;

use App\Game\Contracts\CharacterResetRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterResetOperation;
use App\Reset\CharacterResetResult;
use App\Reset\Exceptions\CharacterResetRejected;
use App\Reset\InventoryPolicy;
use App\Reset\ResetBlockReason;
use App\Reset\ResetProfileReader;
use Throwable;

/**
 * Coordinates idempotency, audit persistence and the transactional game reset.
 */
final readonly class ExecuteCharacterReset
{
    /**
     * Creates the reset action with its policy reader and game write boundary.
     */
    public function __construct(
        private ResetProfileReader $resetProfileReader,
        private CharacterResetRepository $characterResetRepository,
    ) {}

    /**
     * Executes one at-most-once reset request for an authenticated account.
     */
    public function handle(
        string $accountId,
        string $characterName,
        string $idempotencyKey,
    ): CharacterResetResult {
        $this->ensureGameWritesAreEnabled();

        $existingOperation = CharacterResetOperation::query()
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
        $operation = CharacterResetOperation::query()->create([
            'idempotency_key' => $idempotencyKey,
            'account_id' => $accountId,
            'character_name' => $characterName,
            'status' => CharacterOperationStatus::Pending,
            'settings_snapshot' => $settingsSnapshot,
        ]);

        try {
            $result = $this->characterResetRepository->reset(
                accountId: $accountId,
                characterName: $characterName,
                settings: $settings,
            );
        } catch (CharacterResetRejected $exception) {
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
            'before_snapshot' => $result->beforeSnapshot,
            'after_snapshot' => $result->afterSnapshot,
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
            throw new CharacterResetRejected(
                ResetBlockReason::WritesDisabled,
                503,
            );
        }
    }

    /**
     * Returns a completed prior result or safely rejects an unfinished duplicate.
     */
    private function resultFromExistingOperation(
        CharacterResetOperation $operation,
        string $accountId,
        string $characterName,
    ): CharacterResetResult {
        if (
            $operation->account_id !== $accountId
            || $operation->character_name !== $characterName
        ) {
            throw new CharacterResetRejected(
                ResetBlockReason::IdempotencyConflict,
                409,
            );
        }

        if (
            $operation->status !== CharacterOperationStatus::Completed
            || ! is_array($operation->before_snapshot)
            || ! is_array($operation->after_snapshot)
        ) {
            throw new CharacterResetRejected(
                ResetBlockReason::OperationInProgress,
                409,
            );
        }

        $settingsSnapshot = $operation->settings_snapshot;
        $beforeSnapshot = $operation->before_snapshot;
        $afterSnapshot = $operation->after_snapshot;
        $inventoryPolicy = InventoryPolicy::tryFrom(
            (string) ($settingsSnapshot['inventory_policy'] ?? ''),
        ) ?? InventoryPolicy::KeepAll;

        return new CharacterResetResult(
            characterName: $operation->character_name,
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
            zenCost: (int) $beforeSnapshot['zen'] - (int) $afterSnapshot['zen'],
            statsCleared: (bool) ($settingsSnapshot['clear_stats'] ?? false),
            inventoryPolicy: $inventoryPolicy,
        );
    }
}
