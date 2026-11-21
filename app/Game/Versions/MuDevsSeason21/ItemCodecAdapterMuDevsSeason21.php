<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Contracts\ItemCodec;
use App\Game\Data\ItemSlot;
use InvalidArgumentException;

/**
 * Bridges native S21 records into shared item storage without losing extension bytes.
 */
final readonly class ItemCodecAdapterMuDevsSeason21 implements ItemCodec
{
    public const SLOT_BYTES = 25;

    /**
     * Uses the independently verified native decoder and source catalog.
     */
    public function __construct(
        private ItemCodecMuDevsSeason21 $codec,
        private ItemCatalogAdapterMuDevsSeason21 $catalog,
        private WarehousePreviewMuDevsSeason21 $preview,
        private ItemCatalogMuDevsSeason21 $nativeCatalog,
        private ItemTransferRulesMuDevsSeason21 $transferRules,
    ) {}

    /**
     * Decodes complete native records and enriches their shared presentation metadata.
     *
     * Unknown nonempty headers fail closed instead of being treated as free cells.
     *
     * @return list<ItemSlot>
     */
    public function decode(string $binary, int $expectedSlots): array
    {
        return $this->sharedSlots($this->codec->decode($binary, $expectedSlots), false);
    }

    /**
     * Displays unknown occupied records without presenting them as empty or transferable cells.
     *
     * @return list<ItemSlot>
     */
    public function decodeForDisplay(string $binary, int $expectedSlots): array
    {
        return $this->sharedSlots($this->codec->decode($binary, $expectedSlots), true);
    }

    /**
     * Enriches native records while keeping unknown display-only positions occupied.
     *
     * @param  list<ItemRecordMuDevsSeason21>  $records
     * @return list<ItemSlot>
     */
    private function sharedSlots(array $records, bool $allowUnknownIdentity): array
    {
        $slots = [];

        foreach ($records as $record) {
            $unknownIdentity = ! $record->isEmpty && $record->code() === null;

            if ($unknownIdentity && ! $allowUnknownIdentity) {
                throw new InvalidArgumentException('The S21 item uses an unsupported identity header.');
            }

            $presentation = $record->isEmpty ? null : $this->preview->items([$record])[0];
            $nativePresentation = $presentation === null ? null : [
                'title' => $presentation['title'],
                'details' => $presentation['details'],
            ];
            if ($unknownIdentity) {
                $nativePresentation = [
                    'title' => __('ui.s21_warehouse.unknown_item'),
                    'details' => [['label' => __('ui.s21_warehouse.unknown'), 'value' => __('ui.web_storage.errors.unsupported_item')]],
                ];
            }

            $slot = new ItemSlot(
                slot: $record->slot,
                binary: $record->binary(),
                group: $record->group,
                index: $record->index,
                level: $unknownIdentity ? null : $record->level,
                durability: $unknownIdentity ? null : $record->durability,
                optionLevel: $unknownIdentity ? null : $record->optionLevel,
                hasSkill: ! $unknownIdentity && $record->hasSkill,
                hasLuck: ! $unknownIdentity && $record->hasLuck,
                excellentMask: $unknownIdentity ? 0 : $record->excellentMask,
                ancientOption: $unknownIdentity ? 0 : $record->ancientOption,
                serial: $record->isEmpty ? null : strtoupper(bin2hex(substr($record->binary(), 3, 4))),
                harmonyOption: $record->harmonyOption ?? 0,
                socketBonus: $record->socketBonus,
                socketOptions: $record->socketOptions,
                nativePresentation: $nativePresentation,
                name: $unknownIdentity ? __('ui.s21_warehouse.unknown_item') : null,
                identityVerified: ! $unknownIdentity,
                transferable: $record->isEmpty ? null : $this->transferRules->allowsTransfer($record),
            );
            $definition = $record->isEmpty || $unknownIdentity ? null : $this->catalog->definitionFor($record->group, $record->index);
            $slots[] = $definition === null ? $slot : $slot->withDefinition($definition);
        }

        return $slots;
    }

    /**
     * Reassembles full S21 records, including all opaque extension bytes.
     *
     * @param  list<ItemSlot>  $slots
     */
    public function encode(array $slots): string
    {
        $binary = '';

        foreach ($slots as $slot) {
            if (strlen($slot->binary()) !== self::SLOT_BYTES) {
                throw new InvalidArgumentException('S21 storage accepts only 25-byte records.');
            }

            $binary .= $slot->binary();
        }

        return $binary;
    }

    /**
     * Builds only the common header covered by the independent client captures.
     *
     * This pure encoder does not reserve serials or write game records. Native
     * generation routes remain unavailable until their persistence is verified.
     *
     * @param  list<int>  $socketOptions
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
        int $harmonyOption = 0,
        ?int $socketBonus = null,
        array $socketOptions = [],
        bool $has380Option = false,
    ): string {
        $this->assertRange($group, 0, 15, 'group');
        $this->assertRange($index, 0, 511, 'index');
        $this->assertRange($level, 0, 15, 'level');
        $this->assertRange($durability, 0, 255, 'durability');
        $this->assertRange($optionLevel, 0, 7, 'additional option');
        $this->assertRange($excellentMask, 0, 63, 'excellent mask');
        $this->assertRange($ancientOption, 0, 15, 'ancient option');
        $this->assertRange($serial, 1, 2_147_483_647, 'serial');
        $this->assertRange($harmonyOption, 0, 254, 'Harmony option');

        if ($has380Option) {
            throw new InvalidArgumentException('The native S21 380-option header has not been verified.');
        }

        if ($this->nativeCatalog->definitionFor($group, $index) === null) {
            throw new InvalidArgumentException('The item is absent from the native S21 catalog.');
        }

        $maximumSockets = $this->nativeCatalog->maximumSocketsFor($group, $index);

        if (count($socketOptions) > 5 || ($socketBonus !== null && $maximumSockets === 0)) {
            throw new InvalidArgumentException('The item does not support the requested socket layout.');
        }

        if ($maximumSockets > 0 && $harmonyOption !== 0) {
            throw new InvalidArgumentException('Native sockets and Harmony cannot share their option byte.');
        }

        if ($socketBonus !== null) {
            $this->assertRange($socketBonus, 0, 255, 'socket bonus');
        }

        $socketBytes = array_fill(0, 5, 255);

        foreach (array_values($socketOptions) as $position => $value) {
            if (! is_int($value)) {
                throw new InvalidArgumentException('Native socket values must be integers.');
            }

            if ($position >= $maximumSockets && $value !== 255) {
                throw new InvalidArgumentException('The item does not support this socket position.');
            }

            $this->assertRange($value, 0, 255, 'socket value');
            $socketBytes[$position] = $value;
        }

        $optionByte = ($level << 3) | ($optionLevel & 3);
        $optionByte |= $hasSkill ? 0x80 : 0;
        $optionByte |= $hasLuck ? 0x04 : 0;
        $excellentByte = $excellentMask | (($optionLevel & 4) << 4) | (($index & 0x100) >> 1);
        $advancedByte = $maximumSockets > 0 ? ($socketBonus ?? 255) : $harmonyOption;

        return chr($index & 0xFF)
            .chr($optionByte)
            .chr($durability)
            .pack('N', $serial)
            .chr($excellentByte)
            .chr($ancientOption)
            .chr($group << 4)
            .chr($advancedByte)
            .pack('C*', ...$socketBytes)
            .str_repeat("\xFF", 9);
    }

    /**
     * Rejects values that would overflow a client-verified native header field.
     */
    private function assertRange(int $value, int $minimum, int $maximum, string $field): void
    {
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException("The S21 {$field} is outside its supported native range.");
        }
    }
}
