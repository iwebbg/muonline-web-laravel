<?php

namespace App\PkClear\Actions;

use App\Game\Contracts\CharacterPkClearRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterPkClearOperation;
use App\PkClear\CharacterPkClearResult;
use App\PkClear\Exceptions\CharacterPkClearRejected;
use App\PkClear\PkClearBlockReason;
use App\Reset\ResetProfileReader;
use Throwable;

/**
 * Coordinates idempotency, audit persistence and transactional PK clear.
 */
final readonly class ExecuteCharacterPkClear
{
    /**
     * Creates the PK clear action with its policy and game write boundary.
     */
    public function __construct(
        private ResetProfileReader $resetProfileReader,
        private CharacterPkClearRepository $pkClearRepository,
    ) {}

    /**
     * Executes one at-most-once PK clear for an authenticated account.
     */
    public function handle(
        string $accountId,
        string $characterName,
        string $idempotencyKey,
    ): CharacterPkClearResult {
        $this->ensureGameWritesAreEnabled();

        $existingOperation = CharacterPkClearOperation::query()
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
        $operation = CharacterPkClearOperation::query()->create([
            'idempotency_key' => $idempotencyKey,
            'account_id' => $accountId,
            'character_name' => $characterName,
            'status' => CharacterOperationStatus::Pending,
            'rule_snapshot' => [
                'game_version' => (string) config('game.profile.version'),
                'enabled' => $settings->pkClearEnabled,
                'required_level' => $settings->pkClearRequiredLevel,
                'base_zen_cost' => $settings->pkClearBaseZenCost,
                'price_mode' => $settings->pkClearPriceMode->value,
                'target_pk_level' => 3,
                'profile_revision' => $this->resetProfileReader->revision(),
                'source' => 'darksteam_97d99i',
            ],
        ]);

        try {
            $result = $this->pkClearRepository->clearPk(
                accountId: $accountId,
                characterName: $characterName,
                settings: $settings,
            );
        } catch (CharacterPkClearRejected $exception) {
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
            throw new CharacterPkClearRejected(
                PkClearBlockReason::WritesDisabled,
                503,
            );
        }
    }

    /**
     * Returns a completed prior result or safely rejects an unfinished duplicate.
     */
    private function resultFromExistingOperation(
        CharacterPkClearOperation $operation,
        string $accountId,
        string $characterName,
    ): CharacterPkClearResult {
        if (
            $operation->account_id !== $accountId
            || $operation->character_name !== $characterName
        ) {
            throw new CharacterPkClearRejected(
                PkClearBlockReason::IdempotencyConflict,
                409,
            );
        }

        if (
            $operation->status !== CharacterOperationStatus::Completed
            || ! is_array($operation->before_snapshot)
            || ! is_array($operation->after_snapshot)
        ) {
            throw new CharacterPkClearRejected(
                PkClearBlockReason::OperationInProgress,
                409,
            );
        }

        $beforeSnapshot = $operation->before_snapshot;
        $afterSnapshot = $operation->after_snapshot;

        return new CharacterPkClearResult(
            characterName: $operation->character_name,
            beforeZen: (int) $beforeSnapshot['zen'],
            afterZen: (int) $afterSnapshot['zen'],
            beforePkLevel: (int) $beforeSnapshot['pk_level'],
            afterPkLevel: (int) $afterSnapshot['pk_level'],
            beforePkCount: (int) $beforeSnapshot['pk_count'],
            afterPkCount: (int) $afterSnapshot['pk_count'],
            beforePkTime: (int) $beforeSnapshot['pk_time'],
            afterPkTime: (int) $afterSnapshot['pk_time'],
        );
    }
}
