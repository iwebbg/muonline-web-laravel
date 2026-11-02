<?php

namespace App\ItemGrants\Actions;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use App\ItemGrants\Exceptions\ItemGrantRejected;
use App\ItemGrants\ItemGrantBlockReason;
use App\ItemGrants\ItemGrantResult;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Models\Administrator;
use App\Models\ItemGrantOperation;
use JsonException;
use Throwable;

/**
 * Coordinates permission checks, serial reservation and replay-safe item delivery.
 */
final readonly class GrantItemToWarehouse
{
    /**
     * Creates the action with version-aware item and warehouse boundaries.
     */
    public function __construct(
        private ItemSerialRepository $itemSerialRepository,
        private ItemEscrowRepository $itemEscrowRepository,
        private ItemCatalog97d99i $catalog,
        private ItemCodec97d99i $codec,
    ) {}

    /**
     * Grants one catalog item to an offline account's active warehouse.
     *
     * @param  array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}  $options
     *
     * @throws JsonException When the canonical request snapshot cannot be encoded.
     */
    public function execute(
        Administrator $administrator,
        string $accountId,
        array $options,
        string $reason,
        string $idempotencyKey,
    ): ItemGrantResult {
        $this->ensureAllowed($administrator);
        $definition = $this->catalog->definitionFor(
            $options['group'],
            $options['index'],
        );

        if ($definition === null) {
            throw new ItemGrantRejected(ItemGrantBlockReason::UnknownItem);
        }

        $requestHash = hash('sha256', json_encode([
            'administrator_id' => $administrator->getKey(),
            'account_id' => $accountId,
            'options' => $options,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR));
        $existingOperation = ItemGrantOperation::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingOperation !== null) {
            return $this->resultFromExistingOperation($existingOperation, $requestHash);
        }

        $operation = ItemGrantOperation::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'request_hash' => $requestHash,
                'administrator_id' => $administrator->getKey(),
                'account_id' => $accountId,
                'item_code' => $definition->code(),
                'item_name' => $definition->name,
                'status' => CharacterOperationStatus::Pending,
                'reason' => $reason,
                'context_snapshot' => [
                    'administrator_id' => $administrator->getKey(),
                    'reason' => $reason,
                    'game_version' => (string) config('game.profile.version'),
                    'server_profile' => (string) config('game.server_profile'),
                    'options' => $options,
                ],
                'before_snapshot' => [
                    'delivered' => false,
                ],
            ],
        );

        if (! $operation->wasRecentlyCreated) {
            return $this->resultFromExistingOperation($operation, $requestHash);
        }

        try {
            $serial = $this->itemSerialRepository->nextSerial();
            $itemBinary = $this->codec->create(
                group: $options['group'],
                index: $options['index'],
                level: $options['level'],
                durability: $options['durability'],
                optionLevel: $options['option_level'],
                hasSkill: $options['has_skill'],
                hasLuck: $options['has_luck'],
                excellentMask: $options['excellent_mask'],
                ancientOption: $options['ancient_option'],
                serial: $serial,
            );
            $operation->update([
                'serial' => $serial,
                'item_binary' => $itemBinary,
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($operation, 'serial_reservation_failed');

            throw $exception;
        }

        return $this->deliver($operation->refresh(), $definition->width, $definition->height);
    }

    /**
     * Delivers the exact reserved item bytes and commits the audit result.
     */
    private function deliver(
        ItemGrantOperation $operation,
        int $width,
        int $height,
    ): ItemGrantResult {
        if (! is_string($operation->item_binary) || $operation->serial === null) {
            $this->markFailed($operation, 'missing_item_bytes');

            throw new ItemGrantRejected(ItemGrantBlockReason::PreviousFailure, 409);
        }

        try {
            $this->itemEscrowRepository->deposit(
                accountId: $operation->account_id,
                itemBinary: $operation->item_binary,
                width: $width,
                height: $height,
            );
        } catch (MarketListingRejected $exception) {
            $reason = $this->mapWarehouseFailure($exception->reason);

            $this->markFailed($operation, $reason->value);

            throw new ItemGrantRejected($reason);
        } catch (Throwable $exception) {
            $this->markFailed($operation, 'unexpected_error');

            throw $exception;
        }

        $operation->update([
            'status' => CharacterOperationStatus::Completed,
            'after_snapshot' => [
                'delivered' => true,
                'destination' => 'active_warehouse',
                'serial' => $operation->serial,
            ],
            'completed_at' => now(),
        ]);

        return $this->result($operation->refresh());
    }

    /**
     * Requires the granular item capability and both explicit game-write switches.
     */
    private function ensureAllowed(Administrator $administrator): void
    {
        if (! $administrator->hasPermission(AdministratorPermission::GrantItems)) {
            throw new ItemGrantRejected(ItemGrantBlockReason::PermissionDenied, 403);
        }

        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new ItemGrantRejected(ItemGrantBlockReason::WritesDisabled, 503);
        }
    }

    /**
     * Resolves a prior idempotent request without reserving another serial.
     */
    private function resultFromExistingOperation(
        ItemGrantOperation $operation,
        string $requestHash,
    ): ItemGrantResult {
        if (! hash_equals($operation->request_hash, $requestHash)) {
            throw new ItemGrantRejected(ItemGrantBlockReason::IdempotencyConflict, 409);
        }

        if ($operation->status === CharacterOperationStatus::Completed) {
            return $this->result($operation);
        }

        if ($operation->status === CharacterOperationStatus::Failed) {
            throw new ItemGrantRejected(ItemGrantBlockReason::PreviousFailure, 409);
        }

        throw new ItemGrantRejected(ItemGrantBlockReason::OperationInProgress, 409);
    }

    /**
     * Maps shared warehouse safety failures to item-grant language.
     */
    private function mapWarehouseFailure(
        MarketListingBlockReason $reason,
    ): ItemGrantBlockReason {
        return match ($reason) {
            MarketListingBlockReason::CharacterOnline => ItemGrantBlockReason::AccountOnline,
            MarketListingBlockReason::WarehouseNotFound => ItemGrantBlockReason::WarehouseNotFound,
            MarketListingBlockReason::StorageFull => ItemGrantBlockReason::WarehouseFull,
            default => ItemGrantBlockReason::PreviousFailure,
        };
    }

    /**
     * Marks a pending grant as failed while preserving its exact retry evidence.
     */
    private function markFailed(ItemGrantOperation $operation, string $failureCode): void
    {
        $operation->update([
            'status' => CharacterOperationStatus::Failed,
            'failure_code' => $failureCode,
            'completed_at' => now(),
        ]);
    }

    /**
     * Converts a completed operation into the endpoint result contract.
     */
    private function result(ItemGrantOperation $operation): ItemGrantResult
    {
        return new ItemGrantResult(
            operationId: (int) $operation->getKey(),
            accountId: $operation->account_id,
            itemCode: $operation->item_code,
            itemName: $operation->item_name,
            serial: (int) $operation->serial,
        );
    }
}
