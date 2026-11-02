<?php

namespace App\CharacterMove\Actions;

use App\CharacterMove\CharacterMoveBlockReason;
use App\CharacterMove\CharacterMoveResult;
use App\CharacterMove\Exceptions\CharacterMoveRejected;
use App\Game\Contracts\CharacterMoveRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Game\Versions\Version97d99i\MapNameMapper;
use App\Models\CharacterMoveOperation;
use App\Reset\ResetProfileReader;
use Throwable;

/**
 * Coordinates write guards, idempotency and auditing for Move to Town.
 */
final readonly class ExecuteCharacterMove
{
    /**
     * Creates the move action with its policy reader and game write boundary.
     */
    public function __construct(
        private ResetProfileReader $resetProfileReader,
        private CharacterMoveRepository $characterMoveRepository,
        private MapNameMapper $mapNameMapper,
    ) {}

    /**
     * Executes one at-most-once move for an authenticated account character.
     */
    public function handle(
        string $accountId,
        string $characterName,
        string $idempotencyKey,
    ): CharacterMoveResult {
        $this->ensureGameWritesAreEnabled();

        $existingOperation = CharacterMoveOperation::query()
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
        $operation = CharacterMoveOperation::query()->create([
            'idempotency_key' => $idempotencyKey,
            'account_id' => $accountId,
            'character_name' => $characterName,
            'status' => CharacterOperationStatus::Pending,
            'rule_snapshot' => [
                'game_version' => (string) config('game.profile.version'),
                'enabled' => $settings->moveToTownEnabled,
                'destination_map' => $settings->moveToTownMap,
                'destination_x' => $settings->moveToTownX,
                'destination_y' => $settings->moveToTownY,
                'zen_cost' => $settings->moveToTownZenCost,
                'profile_revision' => $this->resetProfileReader->revision(),
                'source' => 'darksteam_97d99i_gate',
            ],
        ]);

        try {
            $result = $this->characterMoveRepository->moveToTown(
                accountId: $accountId,
                characterName: $characterName,
                settings: $settings,
            );
        } catch (CharacterMoveRejected $exception) {
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
     * Rejects mutations unless both explicit game-write flags permit changes.
     */
    private function ensureGameWritesAreEnabled(): void
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new CharacterMoveRejected(
                CharacterMoveBlockReason::WritesDisabled,
                503,
            );
        }
    }

    /**
     * Returns a completed prior result or rejects an incompatible duplicate.
     */
    private function resultFromExistingOperation(
        CharacterMoveOperation $operation,
        string $accountId,
        string $characterName,
    ): CharacterMoveResult {
        if (
            $operation->account_id !== $accountId
            || $operation->character_name !== $characterName
        ) {
            throw new CharacterMoveRejected(
                CharacterMoveBlockReason::IdempotencyConflict,
                409,
            );
        }

        if (
            $operation->status !== CharacterOperationStatus::Completed
            || ! is_array($operation->before_snapshot)
            || ! is_array($operation->after_snapshot)
        ) {
            throw new CharacterMoveRejected(
                CharacterMoveBlockReason::OperationInProgress,
                409,
            );
        }

        $beforeSnapshot = $operation->before_snapshot;
        $afterSnapshot = $operation->after_snapshot;

        return new CharacterMoveResult(
            characterName: $operation->character_name,
            beforeMap: (int) $beforeSnapshot['map'],
            beforeX: (int) $beforeSnapshot['x'],
            beforeY: (int) $beforeSnapshot['y'],
            beforeZen: (int) $beforeSnapshot['zen'],
            afterMap: (int) $afterSnapshot['map'],
            afterX: (int) $afterSnapshot['x'],
            afterY: (int) $afterSnapshot['y'],
            afterZen: (int) $afterSnapshot['zen'],
            afterMapName: $this->mapNameMapper->nameFor((int) $afterSnapshot['map']),
        );
    }
}
