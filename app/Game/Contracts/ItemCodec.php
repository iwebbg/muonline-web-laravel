<?php

namespace App\Game\Contracts;

use App\Game\Data\ItemSlot;

/**
 * Encodes and decodes native item bytes for the active game version.
 */
interface ItemCodec
{
    /**
     * Decodes an exact-sized native storage blob.
     *
     * @return list<ItemSlot>
     */
    public function decode(string $binary, int $expectedSlots): array;

    /**
     * Rebuilds a native storage blob without changing untouched bytes.
     *
     * @param  list<ItemSlot>  $slots
     */
    public function encode(array $slots): string;

    /**
     * Creates one native item from validated administrator or shop options.
     *
     * @param  list<int>  $socketOptions
     */
    public function create(
        int $group,
        int $index,
        int $level,
        int $durability,
        int $optionLevel,
        bool $hasSkill,
        bool $hasLuck,
        int $excellentMask,
        int $ancientOption,
        int $serial,
        int $harmonyOption = 0,
        ?int $socketBonus = null,
        array $socketOptions = [],
        bool $has380Option = false,
    ): string;
}
