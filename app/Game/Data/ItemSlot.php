<?php

namespace App\Game\Data;

use InvalidArgumentException;

/**
 * Represents one decoded version-owned item slot while retaining its original bytes.
 */
final readonly class ItemSlot
{
    /**
     * Creates an immutable item slot decoded by one version-owned codec.
     *
     * @param  list<int>  $socketOptions
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
        public ?string $name = null,
        public int $width = 1,
        public int $height = 1,
        public ?string $image = null,
        public int $excellentType = 0,
        public int $optionType = 0,
        public int $harmonyOption = 0,
        public ?int $socketBonus = null,
        public array $socketOptions = [],
        public bool $has380Option = false,
    ) {
        if (! in_array(strlen($this->binary), [10, 16], true)) {
            throw new InvalidArgumentException('An item slot must contain exactly 10 or 16 bytes.');
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
            name: $this->name,
            width: $this->width,
            height: $this->height,
            image: $this->image,
            excellentType: $this->excellentType,
            optionType: $this->optionType,
            harmonyOption: $this->harmonyOption,
            socketBonus: $this->socketBonus,
            socketOptions: $this->socketOptions,
            has380Option: $this->has380Option,
        );
    }

    /**
     * Returns the decoded item enriched with shared version catalog metadata.
     */
    public function withDefinition(ItemDefinition $definition): self
    {
        return new self(
            slot: $this->slot,
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
            name: $definition->name,
            width: $definition->width,
            height: $definition->height,
            image: $definition->image,
            excellentType: $definition->excellentType,
            optionType: $definition->optionType,
            harmonyOption: $this->harmonyOption,
            socketBonus: $this->socketBonus,
            socketOptions: $this->socketOptions,
            has380Option: $this->has380Option,
        );
    }

    /**
     * Returns the stable group:index identifier shared by every item feature.
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
     * @return array{slot: int, code: string, name: string, width: int, height: int, image: string|null, excellentType: int, optionType: int, rarity: string, level: int, durability: int, optionLevel: int, hasSkill: bool, hasLuck: bool, excellentMask: int, ancientOption: int, harmonyOption: int, socketBonus: int|null, socketOptions: list<int>, has380Option: bool}
     */
    public function toArray(): array
    {
        return [
            'slot' => $this->slot,
            'code' => $this->code(),
            'name' => $this->name ?? 'Item '.$this->code(),
            'width' => $this->width,
            'height' => $this->height,
            'image' => $this->image,
            'excellentType' => $this->excellentType,
            'optionType' => $this->optionType,
            'rarity' => $this->rarity(),
            'level' => $this->level ?? 0,
            'durability' => $this->durability ?? 0,
            'optionLevel' => $this->optionLevel ?? 0,
            'hasSkill' => $this->hasSkill,
            'hasLuck' => $this->hasLuck,
            'excellentMask' => $this->excellentMask,
            'ancientOption' => $this->ancientOption,
            'harmonyOption' => $this->harmonyOption,
            'socketBonus' => $this->socketBonus,
            'socketOptions' => $this->socketOptions,
            'has380Option' => $this->has380Option,
        ];
    }
}
