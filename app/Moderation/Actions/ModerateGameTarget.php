<?php

namespace App\Moderation\Actions;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\ModerationRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\Administrator;
use App\Models\ModerationOperation;
use App\Moderation\Exceptions\ModerationRejected;
use App\Moderation\ModerationAction;
use App\Moderation\ModerationBlockReason;
use App\Moderation\ModerationResult;
use App\Moderation\ModerationTargetType;
use Throwable;

/**
 * Coordinates permission checks, idempotency and auditing for game moderation.
 */
final readonly class ModerateGameTarget
{
    /**
     * Creates the action with its version-specific game write boundary.
     */
    public function __construct(private ModerationRepository $moderationRepository) {}

    /**
     * Executes one at-most-once account or character moderation operation.
     */
    public function handle(
        Administrator $administrator,
        ModerationTargetType $targetType,
        string $targetIdentifier,
        ModerationAction $action,
        string $reason,
        string $idempotencyKey,
    ): ModerationResult {
        $this->ensurePermission($administrator, $targetType);
        $this->ensureGameWritesAreEnabled();

        $existingOperation = ModerationOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOperation !== null) {
            return $this->resultFromExistingOperation(
                operation: $existingOperation,
                administrator: $administrator,
                targetType: $targetType,
                targetIdentifier: $targetIdentifier,
                action: $action,
            );
        }

        $operation = ModerationOperation::query()->create([
            'idempotency_key' => $idempotencyKey,
            'administrator_id' => $administrator->getKey(),
            'target_type' => $targetType,
            'target_identifier' => $targetIdentifier,
            'account_id' => $targetType === ModerationTargetType::Account
                ? $targetIdentifier
                : null,
            'character_name' => $targetType === ModerationTargetType::Character
                ? $targetIdentifier
                : null,
            'action' => $action,
            'status' => CharacterOperationStatus::Pending,
            'reason' => $reason,
            'context_snapshot' => [
                'administrator_id' => $administrator->getKey(),
                'action' => $action->value,
                'reason' => $reason,
                'game_version' => (string) config('game.profile.version'),
                'source' => 'darksteam_97d99i',
            ],
        ]);

        try {
            $result = $targetType === ModerationTargetType::Account
                ? $this->moderationRepository->moderateAccount($targetIdentifier, $action)
                : $this->moderationRepository->moderateCharacter($targetIdentifier, $action);
        } catch (ModerationRejected $exception) {
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
            'account_id' => $result->accountId,
            'before_snapshot' => $result->beforeSnapshot(),
            'after_snapshot' => $result->afterSnapshot(),
            'status' => CharacterOperationStatus::Completed,
            'completed_at' => now(),
        ]);

        return $result;
    }

    /**
     * Requires the granular capability associated with the requested target type.
     */
    private function ensurePermission(
        Administrator $administrator,
        ModerationTargetType $targetType,
    ): void {
        $permission = $targetType === ModerationTargetType::Account
            ? AdministratorPermission::ModerateAccounts
            : AdministratorPermission::ModerateCharacters;

        if (! $administrator->hasPermission($permission)) {
            throw new ModerationRejected(
                ModerationBlockReason::PermissionDenied,
                403,
            );
        }
    }

    /**
     * Rejects mutations unless both explicit game-write flags permit changes.
     */
    private function ensureGameWritesAreEnabled(): void
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new ModerationRejected(
                ModerationBlockReason::WritesDisabled,
                503,
            );
        }
    }

    /**
     * Returns a committed prior result or rejects an incompatible duplicate.
     */
    private function resultFromExistingOperation(
        ModerationOperation $operation,
        Administrator $administrator,
        ModerationTargetType $targetType,
        string $targetIdentifier,
        ModerationAction $action,
    ): ModerationResult {
        if (
            $operation->administrator_id !== $administrator->getKey()
            || $operation->target_type !== $targetType
            || $operation->target_identifier !== $targetIdentifier
            || $operation->action !== $action
        ) {
            throw new ModerationRejected(
                ModerationBlockReason::IdempotencyConflict,
                409,
            );
        }

        if (
            $operation->status !== CharacterOperationStatus::Completed
            || ! is_array($operation->before_snapshot)
            || ! is_array($operation->after_snapshot)
            || $operation->account_id === null
        ) {
            throw new ModerationRejected(
                ModerationBlockReason::OperationInProgress,
                409,
            );
        }

        return new ModerationResult(
            targetType: $operation->target_type,
            targetIdentifier: $operation->target_identifier,
            accountId: $operation->account_id,
            beforeBlocked: (bool) $operation->before_snapshot['blocked'],
            afterBlocked: (bool) $operation->after_snapshot['blocked'],
            beforeControlCode: (int) $operation->before_snapshot['control_code'],
            afterControlCode: (int) $operation->after_snapshot['control_code'],
        );
    }
}
