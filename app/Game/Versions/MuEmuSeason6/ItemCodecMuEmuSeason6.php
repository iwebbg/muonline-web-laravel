<?php

namespace App\Game\Versions\MuEmuSeason6;

use App\Game\Contracts\ItemCodec;
use App\Game\Data\ItemSlot;
use InvalidArgumentException;

/**
 * Decodes and losslessly re-encodes MuEMU Season 6 sixteen-byte items.
 */
final readonly class ItemCodecMuEmuSeason6 implements ItemCodec
{
    public const SLOT_BYTES = 16;

    private const EMPTY_SLOT = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";

    /**
     * Decodes an exact-sized MuEMU inventory or warehouse blob.
     *
     * @return list<ItemSlot>
     *
     * @throws InvalidArgumentException When the binary length does not match the expected slots.
     */
    public function decode(string $binary, int $expectedSlots): array
    {
        $expectedBytes = $expectedSlots * self::SLOT_BYTES;

        if (strlen($binary) !== $expectedBytes) {
            throw new InvalidArgumentException(
                "Expected {$expectedBytes} MuEMU Season 6 item bytes, received ".strlen($binary).'.',
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
     * Rebuilds a MuEMU storage blob from every untouched native slot.
     *
     * @param  list<ItemSlot>  $slots
     */
    public function encode(array $slots): string
    {
        $binary = '';

        foreach ($slots as $slot) {
            if (strlen($slot->binary()) !== self::SLOT_BYTES) {
                throw new InvalidArgumentException('MuEMU Season 6 storage accepts only sixteen-byte item slots.');
            }

            $binary .= $slot->binary();
        }

        return $binary;
    }

    /**
     * Creates one basic MuEMU item while leaving advanced Season 6 bytes unset.
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
        if ($group < 0 || $group > 15 || $index < 0 || $index > 255) {
            throw new InvalidArgumentException('The item group or index is outside the MuEMU Season 6 format.');
        }

        if ($level < 0 || $level > 15 || $durability < 0 || $durability > 255) {
            throw new InvalidArgumentException('The item level or durability is outside the MuEMU Season 6 format.');
        }

        if ($optionLevel < 0 || $optionLevel > 7 || $excellentMask < 0 || $excellentMask > 63) {
            throw new InvalidArgumentException('The item options are outside the MuEMU Season 6 format.');
        }

        if ($ancientOption < 0 || $ancientOption > 255 || $serial < 1 || $serial > 2_147_483_647) {
            throw new InvalidArgumentException('The item ancient option or serial is outside the supported range.');
        }

        $packedOptions = ($level << 3) | ($optionLevel & 0x03);

        if ($hasSkill) {
            $packedOptions |= 0x80;
        }

        if ($hasLuck) {
            $packedOptions |= 0x04;
        }

        $extendedOptions = $excellentMask;

        if ($optionLevel >= 4) {
            $extendedOptions |= 0x40;
        }

        return pack(
            'CCCNC*',
            $index,
            $packedOptions,
            $durability,
            $serial,
            $extendedOptions,
            $ancientOption,
            $group << 4,
            0,
            255,
            255,
            255,
            255,
            255,
        );
    }

    /**
     * Decodes one native MuEMU item and exposes its Season 6 option bytes.
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
        $extendedOptions = $bytes[7];
        $optionLevel = $packedOptions & 0x03;

        if (($extendedOptions & 0x40) !== 0) {
            $optionLevel += 4;
        }

        $socketBytes = array_slice($bytes, 11, 5);
        $hasSocketData = $this->hasSocketData($bytes[10], $socketBytes);

        return new ItemSlot(
            slot: $slot,
            binary: $binary,
            group: ($bytes[9] >> 4) & 0x0F,
            index: $bytes[0],
            level: ($packedOptions >> 3) & 0x0F,
            durability: $bytes[2],
            optionLevel: $optionLevel,
            hasSkill: ($packedOptions & 0x80) !== 0,
            hasLuck: ($packedOptions & 0x04) !== 0,
            excellentMask: $extendedOptions & 0x3F,
            ancientOption: $bytes[8],
            serial: strtoupper(bin2hex(substr($binary, 3, 4))),
            harmonyOption: $hasSocketData ? 0 : $bytes[10],
            socketBonus: $hasSocketData ? $bytes[10] : null,
            socketOptions: $hasSocketData ? $socketBytes : [],
            has380Option: ($bytes[9] & 0x08) !== 0,
        );
    }

    /**
     * Distinguishes socket payloads from the shared Harmony option byte.
     *
     * MuEMU stores a socket bonus in byte 10 and five socket seeds in bytes
     * 11-15. A non-empty socket seed therefore identifies the byte's meaning
     * without requiring catalog metadata at the binary boundary.
     *
     * @param  list<int>  $socketBytes
     */
    private function hasSocketData(int $optionByte, array $socketBytes): bool
    {
        if ($optionByte === 255) {
            return true;
        }

        foreach ($socketBytes as $socketByte) {
            if ($socketByte !== 255) {
                return true;
            }
        }

        return false;
    }
}
