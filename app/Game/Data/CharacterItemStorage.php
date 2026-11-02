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
     */
    public function __construct(
        public string $characterName,
        public array $equipment,
        public array $inventory,
    ) {}

    /**
     * Serializes equipment and inventory slots for the account endpoint.
     *
     * @return array{characterName: string, equipment: list<array<string, bool|int|string>>, inventory: list<array<string, bool|int|string>>}
     */
    public function toArray(): array
    {
        return [
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
    }
}
