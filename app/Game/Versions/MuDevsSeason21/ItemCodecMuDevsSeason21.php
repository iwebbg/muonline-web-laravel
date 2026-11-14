<?php

namespace App\Game\Versions\MuDevsSeason21;

use InvalidArgumentException;

/**
 * Reads verified S21 headers and losslessly retains all 25 native item bytes.
 *
 * This commissioning codec does not implement ItemCodec or create new items.
 * Advanced options and native groups above 15 require further client fixtures.
 */
final readonly class ItemCodecMuDevsSeason21
{
    public const SLOT_BYTES = 25;

    /**
     * Supplies the native catalog for source-backed names and exact rectangles.
     */
    public function __construct(private ItemCatalogMuDevsSeason21 $catalog) {}

    /**
     * Reads every stored record without truncating hidden or currently unused positions.
     *
     * @return list<ItemRecordMuDevsSeason21>
     */
    public function decode(string $binary, int $expectedSlots): array
    {
        if ($expectedSlots < 0 || strlen($binary) !== $expectedSlots * self::SLOT_BYTES) {
            throw new InvalidArgumentException('The MUDevs S21 blob length does not match its expected 25-byte records.');
        }

        $records = [];

        for ($slot = 0; $slot < $expectedSlots; $slot++) {
            $recordBinary = substr($binary, $slot * self::SLOT_BYTES, self::SLOT_BYTES);
            $records[] = $this->decodeRecord($recordBinary, $slot);
        }

        return $records;
    }

    /**
     * Reassembles an untouched storage snapshot without rebuilding unverified options.
     *
     * @param  list<ItemRecordMuDevsSeason21>  $records  Complete records in native slot order.
     */
    public function encode(array $records): string
    {
        $binary = '';

        foreach ($records as $position => $record) {
            if ($record->slot !== $position) {
                throw new InvalidArgumentException('MUDevs S21 records must retain their complete native slot order.');
            }

            $binary .= $record->binary();
        }

        return $binary;
    }

    /**
     * Decodes client-matched common fields while leaving advanced payloads opaque.
     */
    private function decodeRecord(string $binary, int $slot): ItemRecordMuDevsSeason21
    {
        $isEmpty = $binary === str_repeat("\xFF", self::SLOT_BYTES);
        $packedOptions = ord($binary[1]);
        $extendedOptions = ord($binary[7]);
        $groupHeader = ord($binary[9]);
        $group = null;
        $index = null;

        if (! $isEmpty && ($groupHeader & 0x0F) === 0) {
            $group = $groupHeader >> 4;
            $index = ord($binary[0]) | (($extendedOptions & 0x80) << 1);
        }

        $metadata = null;
        $socketBonus = null;
        $socketOptions = [];

        if ($group !== null && $index !== null) {
            $metadata = $this->catalog->definitionFor($group, $index);

            if ($this->catalog->maximumSocketsFor($group, $index) > 0) {
                $socketBonus = ord($binary[10]);
                $socketOptions = array_values(unpack('C*', substr($binary, 11, 5)));
            }
        }

        return new ItemRecordMuDevsSeason21(
            slot: $slot,
            binary: $binary,
            isEmpty: $isEmpty,
            group: $group,
            index: $index,
            level: $isEmpty ? null : (($packedOptions >> 3) & 0x0F),
            durability: $isEmpty ? null : ord($binary[2]),
            optionLevel: $isEmpty ? null : (($packedOptions & 0x03) | (($extendedOptions & 0x40) >> 4)),
            hasSkill: ! $isEmpty && ($packedOptions & 0x80) !== 0,
            hasLuck: ! $isEmpty && ($packedOptions & 0x04) !== 0,
            excellentMask: $isEmpty ? 0 : ($extendedOptions & 0x3F),
            metadata: $metadata,
            socketBonus: $socketBonus,
            socketOptions: $socketOptions,
        );
    }
}
