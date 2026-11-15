<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Contracts\ItemConstructionPolicy;
use App\Game\Contracts\ItemGrantRules;
use App\Market\Exceptions\MarketListingRejected;
use InvalidArgumentException;

/**
 * Defines native construction limits and rejects items whose dependent state cannot be created.
 */
final class ItemGrantRulesMuDevsSeason21 implements ItemConstructionPolicy, ItemGrantRules
{
    /**
     * Uses the independently captured encoder and native custody policy.
     */
    public function __construct(
        private readonly ItemCodecAdapterMuDevsSeason21 $codec,
        private readonly ItemTransferRulesMuDevsSeason21 $transferRules,
        private readonly ItemOptionsMuDevsSeason21 $itemOptions,
    ) {}

    /**
     * Returns the nine-bit native identity limit.
     */
    public function maxItemIndex(): int
    {
        return 511;
    }

    /**
     * Returns the four-bit native upgrade limit.
     */
    public function maxItemLevel(): int
    {
        return 15;
    }

    /**
     * Returns the three-bit native additional-option limit.
     */
    public function maxOptionLevel(): int
    {
        return 7;
    }

    /**
     * Returns every accepted upgrade level.
     *
     * @return list<int>
     */
    public function itemLevels(): array
    {
        return range(0, $this->maxItemLevel());
    }

    /**
     * Returns every accepted additional-option level.
     *
     * @return list<int>
     */
    public function optionLevels(): array
    {
        return range(0, $this->maxOptionLevel());
    }

    /**
     * Validates source-backed options and a serial-free candidate before any game write.
     *
     * @param  array<string, int|bool|string|null>  $options
     */
    public function allowsConstruction(array $options): bool
    {
        $identity = explode(':', (string) ($options['item_code'] ?? ''), 2);
        $group = (int) ($options['group'] ?? $identity[0] ?? -1);
        $index = (int) ($options['index'] ?? $identity[1] ?? -1);
        $support = $this->itemOptions->definitionFor($group, $index);
        $excellentMask = (int) ($options['excellent_mask'] ?? 0);

        if ((int) ($options['socket_count'] ?? 0) < 0
            || (int) ($options['socket_count'] ?? 0) > 3
            || (int) ($options['harmony_option'] ?? 0) !== 0
            || ((bool) ($options['has_skill'] ?? false) && ! $support['skill'])
            || ((bool) ($options['has_luck'] ?? false) && ! $support['luck'])
            || ((int) ($options['option_level'] ?? 0) > 0 && ! $support['additional'])
            || ($excellentMask & ~$support['excellentMask']) !== 0
            || (int) ($options['ancient_option'] ?? 0) !== 0) {
            return false;
        }

        try {
            $candidate = $this->codec->create(
                group: $group, index: $index,
                level: (int) ($options['level'] ?? 0), durability: (int) ($options['durability'] ?? 255),
                optionLevel: (int) ($options['option_level'] ?? 0), hasSkill: (bool) ($options['has_skill'] ?? false),
                hasLuck: (bool) ($options['has_luck'] ?? false), excellentMask: $excellentMask,
                ancientOption: 0, serial: 1,
                harmonyOption: (int) ($options['harmony_option'] ?? 0),
                socketOptions: array_fill(0, max(0, min(6, (int) ($options['socket_count'] ?? 0))), 254),
                has380Option: (bool) ($options['has_380_option'] ?? false),
            );
            $this->transferRules->assertTransferable($candidate);
        } catch (InvalidArgumentException|MarketListingRejected) {
            return false;
        }

        return true;
    }
}
