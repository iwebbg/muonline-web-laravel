<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use InvalidArgumentException;

/**
 * Restricts native custody to ordinary equipment, standard wings and physical jewels.
 */
final readonly class ItemTransferRulesMuDevsSeason21
{
    /** @var list<string> */
    private const STANDARD_JEWELS = ['12:15', '14:13', '14:14', '14:16', '14:22'];

    /** Native KindA values for wing generations one through three and second-generation capes. */
    private const STANDARD_WING_KINDS = [23, 24, 25, 26, 27];

    /**
     * Uses the native decoder independently of the shared display bridge.
     */
    public function __construct(private ItemCodecMuDevsSeason21 $codec) {}

    /**
     * Identifies ordinary item families whose custody does not require dependent state.
     */
    public function supportsIdentity(ItemMetadataMuDevsSeason21 $metadata): bool
    {
        $flags = $metadata->sourceAttributes;
        $isEquipment = $metadata->group <= 11
            && $metadata->equipmentSlot >= 0 && $metadata->equipmentSlot <= 11;

        return ($flags['Transaction'] ?? '0') === '1'
            && ($flags['StoreWarehouse'] ?? '0') === '1'
            && ($flags['Serial'] ?? '0') === '1'
            && ($isEquipment || $this->isStandardWing($metadata)
                || in_array($metadata->code(), self::STANDARD_JEWELS, true));
    }

    /**
     * Recognizes source-declared standard wings without admitting wing cores or later generations.
     */
    private function isStandardWing(ItemMetadataMuDevsSeason21 $metadata): bool
    {
        return in_array($metadata->group, [12, 13], true)
            && $metadata->equipmentSlot === 7
            && in_array((int) ($metadata->sourceAttributes['KindA'] ?? -1), self::STANDARD_WING_KINDS, true);
    }

    /**
     * Rejects special dependencies and returns verified native geometry for one custody record.
     *
     * @throws MarketListingRejected When the record cannot be transferred with its bytes alone.
     */
    public function assertTransferable(string $binary): ItemMetadataMuDevsSeason21
    {
        try {
            $record = $this->codec->decode($binary, 1)[0];
        } catch (InvalidArgumentException) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        if (! $this->allowsTransfer($record)) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        return $record->metadata;
    }

    /**
     * Checks native flags without reconstructing bytes or resolving display services.
     *
     * Standard wings use the common header with empty advanced bytes. Later
     * wings, wing cores, pentagrams, earrings, Muun and artifacts remain excluded.
     */
    public function allowsTransfer(ItemRecordMuDevsSeason21 $record): bool
    {
        $metadata = $record->metadata;

        if ($record->isEmpty || $metadata === null) {
            return false;
        }

        $flags = $metadata->sourceAttributes;
        $isEquipment = $record->group <= 11
            && $metadata->equipmentSlot >= 0 && $metadata->equipmentSlot <= 11;
        $isStandardJewel = in_array($record->code(), self::STANDARD_JEWELS, true)
            && $record->level === 0 && $record->durability >= 1
            && $record->durability <= (int) ($flags['ItemOverlap'] ?? 0)
            && ! $record->hasSkill && ! $record->hasLuck
            && $record->optionLevel === 0 && $record->excellentMask === 0 && $record->ancientOption === 0;
        $isStandardWing = $this->isStandardWing($metadata)
            && ! $record->hasSkill && $record->ancientOption === 0
            && substr($record->binary(), 10, 6) === "\x00".str_repeat("\xFF", 5);
        $serial = strtoupper(bin2hex(substr($record->binary(), 3, 4)));

        return $this->supportsIdentity($metadata)
            && ($isEquipment || $isStandardJewel || $isStandardWing)
            && substr($record->binary(), 16) === str_repeat("\xFF", 9)
            && ! in_array($serial, ['00000000', 'FFFFFFFF'], true)
            && $record->ancientOption <= 15;
    }
}
