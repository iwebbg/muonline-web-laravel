<?php

namespace App\Game\Data;

/**
 * Carries one decoded account warehouse vault.
 */
final readonly class WarehouseVault
{
    /**
     * Creates one numbered vault with its occupied slots.
     *
     * @param  list<ItemSlot>  $items
     */
    public function __construct(
        public int $number,
        public bool $active,
        public array $items,
    ) {}

    /**
     * Serializes the vault for the interactive account panel.
     *
     * @return array{number: int, active: bool, items: list<array<string, bool|int|string>>}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'active' => $this->active,
            'items' => array_map(
                static fn (ItemSlot $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }
}
