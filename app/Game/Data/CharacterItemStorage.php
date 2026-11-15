<?php

namespace App\Game\Data;

/**
 * Carries the occupied equipment and bag slots for one owned character.
 */
final readonly class CharacterItemStorage
{
    /**
     * Creates the character storage view from decoded occupied slots.
     *
     * @param  list<ItemSlot>  $equipment
     * @param  list<ItemSlot>  $inventory
     * @param  list<int>|null  $equipmentSlotNumbers
     */
    public function __construct(
        public string $characterName,
        public array $equipment,
        public array $inventory,
        public ?int $inventorySlotCount = null,
        public ?array $equipmentSlotNumbers = null,
    ) {}

    /**
     * Serializes equipment and inventory slots for the account endpoint.
     *
     * @return array{characterName: string, equipment: list<array<string, bool|int|string>>, inventory: list<array<string, bool|int|string>>, inventorySlotCount?: int, equipmentSlotNumbers?: list<int>}
     */
    public function toArray(): array
    {
        $storage = [
            'characterName' => $this->characterName,
            'equipment' => array_map(
                static fn (ItemSlot $item): array => $item->toArray(),
                $this->equipment,
            ),
            'inventory' => array_map(
                static fn (ItemSlot $item): array => $item->toArray(),
                $this->inventory,
            ),
        ];

        if ($this->inventorySlotCount !== null) {
            $storage['inventorySlotCount'] = $this->inventorySlotCount;
        }

        if ($this->equipmentSlotNumbers !== null) {
            $storage['equipmentSlotNumbers'] = $this->equipmentSlotNumbers;
        }

        return $storage;
    }
}
