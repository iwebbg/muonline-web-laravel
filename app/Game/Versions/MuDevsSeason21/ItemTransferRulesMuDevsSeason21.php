<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use InvalidArgumentException;

/**
 * Restricts native custody to ordinary equipment and the five standard physical jewels.
 */
final readonly class ItemTransferRulesMuDevsSeason21
{
    /** @var list<string> */
    private const STANDARD_JEWELS = ['12:15', '14:13', '14:14', '14:16', '14:22'];

    /**
     * Uses the native decoder independently of the shared display bridge.
     */
    public function __construct(private ItemCodecMuDevsSeason21 $codec) {}

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
     * Pentagrams, earrings, wings, Muun and artifacts need separate persistence;
     * their main record alone does not establish complete custody.
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
        $serial = strtoupper(bin2hex(substr($record->binary(), 3, 4)));

        return ($flags['Transaction'] ?? '0') === '1'
            && ($flags['StoreWarehouse'] ?? '0') === '1'
            && ($flags['Serial'] ?? '0') === '1'
            && ($isEquipment || $isStandardJewel)
            && substr($record->binary(), 16) === str_repeat("\xFF", 9)
            && ! in_array($serial, ['00000000', 'FFFFFFFF'], true)
            && $record->ancientOption <= 15;
    }
}
