<?php

namespace App\Game\Data;

/**
 * Carries an owned character inventory and every available account vault.
 */
final readonly class AccountItemStorage
{
    /**
     * Creates the complete read-only item storage response.
     *
     * @param  list<WarehouseVault>  $vaults
     */
    public function __construct(
        public CharacterItemStorage $character,
        public int $warehouseZen,
        public array $vaults,
    ) {}

    /**
     * Serializes the item storage response for JSON transport.
     *
     * @return array{character: array<string, mixed>, warehouse: array{zen: int, vaults: list<array<string, mixed>>}}
     */
    public function toArray(): array
    {
        return [
            'character' => $this->character->toArray(),
            'warehouse' => [
                'zen' => $this->warehouseZen,
                'vaults' => array_map(
                    static fn (WarehouseVault $vault): array => $vault->toArray(),
                    $this->vaults,
                ),
            ],
        ];
    }
}
