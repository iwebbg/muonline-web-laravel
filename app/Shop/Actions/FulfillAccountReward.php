<?php

namespace App\Shop\Actions;

use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Models\AccountReward;
use App\Models\WebStorageItem;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use App\Shop\AccountRewardStatus;
use App\Shop\RewardDeliveryDestination;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageContext;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Delivers one selected reward to the game vault or enabled Web Vault fallback.
 */
final readonly class FulfillAccountReward
{
    /**
     * Creates the fulfillment boundary with version-specific item construction.
     */
    public function __construct(
        private ItemSerialRepository $itemSerialRepository,
        private ItemCodec $codec,
        private MarketItemEscrowRepository $itemEscrowRepository,
        private ModuleManager $moduleManager,
    ) {}

    /**
     * Attempts idempotent delivery while preserving ownership after any failure.
     */
    public function execute(AccountReward $reward): bool
    {
        if ($reward->status === AccountRewardStatus::Claimed) {
            return true;
        }

        if ($reward->status !== AccountRewardStatus::Ready || ! is_array($reward->selected_item_snapshot)) {
            return false;
        }

        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            $this->recordFailure($reward, 'writes_disabled');

            return false;
        }

        try {
            $preparedReward = $this->prepareExactItem($reward);
            $item = $preparedReward->selected_item_snapshot;

            $this->itemEscrowRepository->deposit(
                accountId: $preparedReward->account_id,
                itemBinary: (string) $preparedReward->item_binary,
                width: (int) ($item['item_width'] ?? 1),
                height: (int) ($item['item_height'] ?? 1),
            );

            $preparedReward->update([
                'status' => AccountRewardStatus::Claimed,
                'delivery_destination' => RewardDeliveryDestination::GameVault,
                'delivery_attempts' => $preparedReward->delivery_attempts + 1,
                'last_delivery_attempt_at' => now(),
                'last_delivery_error' => null,
                'claimed_at' => now(),
            ]);

            return true;
        } catch (MarketListingRejected $exception) {
            if ($this->shouldUseWebVault($exception)) {
                return $this->fallbackToWebVault($reward->refresh());
            }

            $this->recordFailure($reward, $exception->reason->value);

            return false;
        } catch (Throwable) {
            $this->recordFailure($reward, 'delivery_failed');

            return false;
        }
    }

    /**
     * Reserves a serial once and persists the exact bytes before delivery.
     */
    private function prepareExactItem(AccountReward $reward): AccountReward
    {
        return DB::transaction(function () use ($reward): AccountReward {
            $lockedReward = AccountReward::query()->whereKey($reward->getKey())->lockForUpdate()->firstOrFail();

            if (is_string($lockedReward->item_binary) && $lockedReward->item_serial !== null) {
                return $lockedReward;
            }

            $item = $lockedReward->selected_item_snapshot;
            $itemCodeParts = array_pad(
                explode(':', (string) ($item['item_code'] ?? ''), 2),
                2,
                '0',
            );
            [$group, $index] = array_map('intval', $itemCodeParts);
            $serial = $this->itemSerialRepository->nextSerial();
            $itemBinary = $this->codec->create(
                group: $group,
                index: $index,
                level: (int) ($item['level'] ?? 0),
                durability: (int) ($item['durability'] ?? 255),
                optionLevel: (int) ($item['option_level'] ?? 0),
                hasSkill: (bool) ($item['has_skill'] ?? false),
                hasLuck: (bool) ($item['has_luck'] ?? false),
                excellentMask: (int) ($item['excellent_mask'] ?? 0),
                ancientOption: (int) ($item['ancient_option'] ?? 0),
                serial: $serial,
            );

            $lockedReward->update([
                'item_serial' => $serial,
                'item_binary' => $itemBinary,
                'item_fingerprint' => hash('sha256', $itemBinary),
            ]);

            return $lockedReward->refresh();
        }, attempts: 3);
    }

    /**
     * Reports whether an unavailable game vault may safely use Web Vault custody.
     */
    private function shouldUseWebVault(MarketListingRejected $exception): bool
    {
        return $this->moduleManager->enabled(ServerModule::WebStorage)
            && in_array($exception->reason, [
                MarketListingBlockReason::CharacterOnline,
                MarketListingBlockReason::StorageFull,
                MarketListingBlockReason::WarehouseNotFound,
            ], true);
    }

    /**
     * Stores the prepared exact bytes in recoverable Web Vault custody once.
     */
    private function fallbackToWebVault(AccountReward $reward): bool
    {
        try {
            DB::transaction(function () use ($reward): void {
                $lockedReward = AccountReward::query()->whereKey($reward->getKey())->lockForUpdate()->firstOrFail();
                $idempotencyKey = Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    'muonline-shop-reward-web-vault:'.$lockedReward->public_id,
                )->toString();
                $item = $lockedReward->selected_item_snapshot;

                WebStorageItem::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'public_id' => (string) Str::uuid(),
                        'request_hash' => hash('sha256', $lockedReward->public_id.'|'.$lockedReward->item_fingerprint),
                        'account_id' => $lockedReward->account_id,
                        'storage_context' => WebStorageContext::WebVault,
                        'source_character_name' => 'Web Shop',
                        'source_location' => ItemStorageLocation::Warehouse,
                        'source_vault' => 0,
                        'source_slot' => 0,
                        'item_binary' => $lockedReward->item_binary,
                        'item_fingerprint' => $lockedReward->item_fingerprint,
                        'escrow_key' => $lockedReward->item_fingerprint,
                        'item_snapshot' => [
                            'code' => $item['item_code'] ?? '',
                            'name' => $item['item_name'] ?? '',
                            'image' => $item['item_image'] ?? null,
                            'width' => $item['item_width'] ?? 1,
                            'height' => $item['item_height'] ?? 1,
                            'level' => $item['level'] ?? 0,
                        ],
                        'status' => WebStorageItemStatus::Available,
                        'sync_attempts' => 0,
                        'stored_at' => now(),
                    ],
                );

                $lockedReward->update([
                    'status' => AccountRewardStatus::Claimed,
                    'delivery_destination' => RewardDeliveryDestination::WebVault,
                    'delivery_attempts' => $lockedReward->delivery_attempts + 1,
                    'last_delivery_attempt_at' => now(),
                    'last_delivery_error' => null,
                    'claimed_at' => now(),
                ]);
            }, attempts: 3);

            return true;
        } catch (Throwable) {
            $this->recordFailure($reward, 'web_vault_fallback_failed');

            return false;
        }
    }

    /**
     * Records a safe retryable fulfillment failure without losing ownership.
     */
    private function recordFailure(AccountReward $reward, string $error): void
    {
        $reward->refresh()->update([
            'delivery_attempts' => $reward->delivery_attempts + 1,
            'last_delivery_attempt_at' => now(),
            'last_delivery_error' => $error,
        ]);
    }
}
