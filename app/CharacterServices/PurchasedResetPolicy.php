<?php

namespace App\CharacterServices;

use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;

/**
 * Carries the immutable reset rules used by one paid reset operation.
 */
final readonly class PurchasedResetPolicy
{
    /**
     * Creates the minimal reset policy required inside the game write boundary.
     */
    public function __construct(
        public int $maximumResets,
        public bool $clearStats,
        public PointsMode $pointsMode,
        public int $pointsPerReset,
        public InventoryPolicy $inventoryPolicy,
    ) {}

    /**
     * Captures the relevant rules from the active reset profile.
     */
    public static function fromResetSettings(ResetProfileSettings $settings): self
    {
        return new self(
            maximumResets: $settings->maximumResets,
            clearStats: $settings->clearStats,
            pointsMode: $settings->pointsMode,
            pointsPerReset: $settings->pointsPerReset,
            inventoryPolicy: $settings->inventoryPolicy,
        );
    }

    /**
     * Restores the immutable rules recorded with a pending operation.
     *
     * @param  array<string, bool|int|string>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self(
            maximumResets: (int) $snapshot['maximum_resets'],
            clearStats: (bool) $snapshot['clear_stats'],
            pointsMode: PointsMode::from((string) $snapshot['points_mode']),
            pointsPerReset: (int) $snapshot['points_per_reset'],
            inventoryPolicy: InventoryPolicy::from((string) $snapshot['inventory_policy']),
        );
    }

    /**
     * Calculates the unallocated points produced by the final purchased reset.
     */
    public function pointsFor(int $resultingResets): int
    {
        if ($this->pointsMode === PointsMode::Fixed) {
            return $this->pointsPerReset;
        }

        return $this->pointsPerReset * max(1, $resultingResets);
    }

    /**
     * Converts the policy to the operation's durable settings snapshot.
     *
     * @return array<string, bool|int|string>
     */
    public function toSnapshot(): array
    {
        return [
            'maximum_resets' => $this->maximumResets,
            'clear_stats' => $this->clearStats,
            'points_mode' => $this->pointsMode->value,
            'points_per_reset' => $this->pointsPerReset,
            'inventory_policy' => $this->inventoryPolicy->value,
        ];
    }
}
