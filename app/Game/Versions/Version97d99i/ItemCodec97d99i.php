<?php

namespace App\Game\Versions\Version97d99i;

use App\Game\Data\ItemSlot;
use InvalidArgumentException;

/**
 * Decodes and losslessly re-encodes Darksteam 97d+99i item blobs.
 */
final readonly class ItemCodec97d99i
{
    public const SLOT_BYTES = 10;

    private const EMPTY_SLOT = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";

    /**
     * Decodes an exact-sized binary storage blob into all of its slots.
     *
     * @return list<ItemSlot>
     *
     * @throws InvalidArgumentException When the blob length does not match the expected slot count.
     */
    public function decode(string $binary, int $expectedSlots): array
    {
        $expectedBytes = $expectedSlots * self::SLOT_BYTES;

        if (strlen($binary) !== $expectedBytes) {
            throw new InvalidArgumentException(
                "Expected {$expectedBytes} item bytes, received ".strlen($binary).'.',
            );
        }

        $slots = [];

        for ($slot = 0; $slot < $expectedSlots; $slot++) {
            $slotBinary = substr($binary, $slot * self::SLOT_BYTES, self::SLOT_BYTES);
            $slots[] = $this->decodeSlot($slotBinary, $slot);
        }

        return $slots;
    }

    /**
     * Rebuilds a storage blob from untouched decoded slot bytes.
     *
     * @param  list<ItemSlot>  $slots
     */
    public function encode(array $slots): string
    {
        $binary = '';

        foreach ($slots as $slot) {
            $binary .= $slot->binary();
        }

        return $binary;
    }

    /**
     * Creates one verified 10-byte item from explicit administrator options.
     *
     * The caller must obtain the serial from the game server's authoritative
     * counter before using this method.
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
    ): string {
        if ($group < 0 || $group > 15 || $index < 0 || $index > 31) {
            throw new InvalidArgumentException('The item group or index is outside the 97d+99i format.');
        }

        if ($level < 0 || $level > 15 || $durability < 0 || $durability > 255) {
            throw new InvalidArgumentException('The item level or durability is outside the 97d+99i format.');
        }

        if ($optionLevel < 0 || $optionLevel > 7 || $excellentMask < 0 || $excellentMask > 63) {
            throw new InvalidArgumentException('The item options are outside the 97d+99i format.');
        }

        if ($ancientOption < 0 || $ancientOption > 255 || $serial < 1 || $serial > 2_147_483_647) {
            throw new InvalidArgumentException('The item ancient option or serial is outside the supported range.');
        }

        $itemIdentifier = (($group % 8) * 32) + $index;
        $packedOptions = ($level << 3) | ($optionLevel & 0x03);

        if ($hasSkill) {
            $packedOptions |= 0x80;
        }

        if ($hasLuck) {
            $packedOptions |= 0x04;
        }

        $extendedOptions = $excellentMask;

        if ($group >= 8) {
            $extendedOptions |= 0x80;
        }

        if ($optionLevel >= 4) {
            $extendedOptions |= 0x40;
        }

        return pack(
            'CCCNC*',
            $itemIdentifier,
            $packedOptions,
            $durability,
            $serial,
            $extendedOptions,
            $ancientOption,
            0,
        );
    }

    /**
     * Decodes one verified 10-byte item slot.
     */
    private function decodeSlot(string $binary, int $slot): ItemSlot
    {
        if ($binary === self::EMPTY_SLOT) {
            return new ItemSlot(
                slot: $slot,
                binary: $binary,
                group: null,
                index: null,
                level: null,
                durability: null,
                optionLevel: null,
                hasSkill: false,
                hasLuck: false,
                excellentMask: 0,
                ancientOption: 0,
                serial: null,
            );
        }

        $bytes = array_values(unpack('C*', $binary));
        $packedOptions = $bytes[1];
        $excellentByte = $bytes[7];
        $group = intdiv($bytes[0], 32);

        if (($excellentByte & 0x80) !== 0) {
            $group += 8;
        }

        $optionLevel = $packedOptions & 0x03;

        if (($excellentByte & 0x40) !== 0) {
            $optionLevel += 4;
        }

        return new ItemSlot(
            slot: $slot,
            binary: $binary,
            group: $group,
            index: $bytes[0] % 32,
            level: ($packedOptions >> 3) & 0x0F,
            durability: $bytes[2],
            optionLevel: $optionLevel,
            hasSkill: ($packedOptions & 0x80) !== 0,
            hasLuck: ($packedOptions & 0x04) !== 0,
            excellentMask: $excellentByte & 0x3F,
            ancientOption: $bytes[8],
            serial: strtoupper(bin2hex(substr($binary, 3, 4))),
        );
    }
}
