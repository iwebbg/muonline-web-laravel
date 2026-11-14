<?php

namespace App\Game\Versions\MuDevsSeason21;

use InvalidArgumentException;

/**
 * Keeps one native S21 record intact while exposing only client-verified header fields.
 */
final readonly class ItemRecordMuDevsSeason21
{
    /**
     * Creates a diagnostic snapshot without inventing advanced option semantics.
     *
     * @param  list<int>  $socketOptions  Raw socket bytes: 254 is an empty socket, 255 is absent.
     */
    public function __construct(
        public int $slot,
        private string $binary,
        public bool $isEmpty,
        public ?int $group,
        public ?int $index,
        public ?int $level,
        public ?int $durability,
        public ?int $optionLevel,
        public bool $hasSkill,
        public bool $hasLuck,
        public int $excellentMask,
        public ?ItemMetadataMuDevsSeason21 $metadata,
        public ?int $socketBonus,
        public array $socketOptions,
    ) {
        if (strlen($binary) !== 25) {
            throw new InvalidArgumentException('A native MUDevs S21 record must contain exactly 25 bytes.');
        }
    }

    /**
     * Returns every original byte, including unverified advanced options and extensions.
     */
    public function binary(): string
    {
        return $this->binary;
    }

    /**
     * Returns a native identity only when the observed legacy group header is supported.
     */
    public function code(): ?string
    {
        if ($this->group === null || $this->index === null) {
            return null;
        }

        return $this->group.':'.$this->index;
    }

    /**
     * Exposes diagnostic metadata separately from transferable/generated item snapshots.
     *
     * @return array{slot: int, code: string|null, name: string|null, width: int|null, height: int|null, image: string|null, level: int|null, durability: int|null, optionLevel: int|null, hasSkill: bool, hasLuck: bool, excellentMask: int, socketBonus: int|null, socketOptions: list<int>, advancedOptionsVerified: bool, nativeHex: string}
     */
    public function toInspectionArray(): array
    {
        return [
            'slot' => $this->slot,
            'code' => $this->code(),
            'name' => $this->metadata?->name,
            'width' => $this->metadata?->width,
            'height' => $this->metadata?->height,
            'image' => $this->metadata?->image,
            'level' => $this->level,
            'durability' => $this->durability,
            'optionLevel' => $this->optionLevel,
            'hasSkill' => $this->hasSkill,
            'hasLuck' => $this->hasLuck,
            'excellentMask' => $this->excellentMask,
            'socketBonus' => $this->socketBonus,
            'socketOptions' => $this->socketOptions,
            'advancedOptionsVerified' => false,
            'nativeHex' => strtoupper(bin2hex($this->binary)),
        ];
    }
}
