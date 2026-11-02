<?php

namespace App\Game\Data;

use InvalidArgumentException;

/**
 * Represents one decoded 10-byte item slot while retaining its original bytes.
 */
final readonly class ItemSlot
{
    /**
     * Creates an immutable item slot decoded from the 97d+99i binary format.
     */
    public function __construct(
        public int $slot,
        private string $binary,
        public ?int $group,
        public ?int $index,
        public ?int $level,
        public ?int $durability,
        public ?int $optionLevel,
        public bool $hasSkill,
        public bool $hasLuck,
        public int $excellentMask,
        public int $ancientOption,
        public ?string $serial,
    ) {
        if (strlen($this->binary) !== 10) {
            throw new InvalidArgumentException('An item slot must contain exactly 10 bytes.');
        }
    }

    /**
     * Returns whether the slot contains no item.
     */
    public function isEmpty(): bool
    {
        return $this->group === null || $this->index === null;
    }

    /**
     * Returns the untouched bytes required for lossless re-encoding.
     */
    public function binary(): string
    {
        return $this->binary;
    }

    /**
     * Returns the same decoded item addressed by a different UI slot number.
     */
    public function withSlot(int $slot): self
    {
        return new self(
            slot: $slot,
            binary: $this->binary,
            group: $this->group,
            index: $this->index,
            level: $this->level,
            durability: $this->durability,
            optionLevel: $this->optionLevel,
            hasSkill: $this->hasSkill,
            hasLuck: $this->hasLuck,
            excellentMask: $this->excellentMask,
            ancientOption: $this->ancientOption,
            serial: $this->serial,
        );
    }

    /**
     * Returns the stable group:index identifier used until a version catalog is installed.
     */
    public function code(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return $this->group.':'.$this->index;
    }

    /**
     * Classifies the item for the existing account-panel visual treatment.
     */
    public function rarity(): string
    {
        if ($this->ancientOption > 0) {
            return 'ancient';
        }

        if ($this->excellentMask > 0) {
            return 'excellent';
        }

        if (($this->level ?? 0) >= 9 || $this->hasLuck) {
            return 'rare';
        }

        return 'normal';
    }

    /**
     * Serializes an occupied slot for the authenticated account interface.
     *
     * @return array{slot: int, code: string, name: string, rarity: string, level: int, durability: int, optionLevel: int, hasSkill: bool, hasLuck: bool, excellentMask: int, ancientOption: int}
     */
    public function toArray(): array
    {
        return [
            'slot' => $this->slot,
            'code' => $this->code(),
            'name' => 'Item '.$this->code(),
            'rarity' => $this->rarity(),
            'level' => $this->level ?? 0,
            'durability' => $this->durability ?? 0,
            'optionLevel' => $this->optionLevel ?? 0,
            'hasSkill' => $this->hasSkill,
            'hasLuck' => $this->hasLuck,
            'excellentMask' => $this->excellentMask,
            'ancientOption' => $this->ancientOption,
        ];
    }
}
