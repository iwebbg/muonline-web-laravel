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
     * @param  int  $socketExtensionFlags  Raw upper shared-header bits for socket context; only captured combinations are interpreted.
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
        public int $ancientOption,
        public ?int $ancientSetIndex,
        public ?string $ancientSetName,
        public ?int $harmonyOption,
        public ?ItemMetadataMuDevsSeason21 $metadata,
        public ?int $socketBonus,
        public array $socketOptions,
        public int $socketExtensionFlags = 0,
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
     * @return array{slot: int, code: string|null, name: string|null, width: int|null, height: int|null, image: string|null, level: int|null, durability: int|null, optionLevel: int|null, hasSkill: bool, hasLuck: bool, excellentMask: int, ancientOption: int, ancientSetIndex: int|null, ancientSetName: string|null, harmonyOption: int|null, harmonyIndex: int|null, harmonyLevel: int|null, socketBonus: int|null, socketOptions: list<int>, socketExtensionFlags: int, advancedOptionsVerified: bool, nativeHex: string}
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
            'ancientOption' => $this->ancientOption,
            'ancientSetIndex' => $this->ancientSetIndex,
            'ancientSetName' => $this->ancientSetName,
            'harmonyOption' => $this->harmonyOption,
            'harmonyIndex' => $this->harmonyOption === null ? null : $this->harmonyOption >> 4,
            'harmonyLevel' => $this->harmonyOption === null ? null : $this->harmonyOption & 0x0F,
            'socketBonus' => $this->socketBonus,
            'socketOptions' => $this->socketOptions,
            'socketExtensionFlags' => $this->socketExtensionFlags,
            'advancedOptionsVerified' => false,
            'nativeHex' => strtoupper(bin2hex($this->binary)),
        ];
    }
}
