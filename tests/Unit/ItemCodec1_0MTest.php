<?php

namespace Tests\Unit;

use App\Game\Versions\Version1_0M\ItemCodec1_0M;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the isolated DarksTeam 1.0M ten-byte item codec contract.
 */
final class ItemCodec1_0MTest extends TestCase
{
    /**
     * Confirms the legacy group extension and option bits from one known layout.
     */
    public function test_decodes_a_known_ten_byte_item(): void
    {
        $binary = hex2bin('1F5B2AAABBCCDDC1037E');

        self::assertIsString($binary);

        $item = (new ItemCodec1_0M)->decode($binary, 1)[0];

        self::assertFalse($item->isEmpty());
        self::assertSame(8, $item->group);
        self::assertSame(31, $item->index);
        self::assertSame(11, $item->level);
        self::assertSame(42, $item->durability);
        self::assertSame(7, $item->optionLevel);
        self::assertFalse($item->hasSkill);
        self::assertFalse($item->hasLuck);
        self::assertSame(1, $item->excellentMask);
        self::assertSame(3, $item->ancientOption);
        self::assertSame('AABBCCDD', $item->serial);
    }

    /**
     * Confirms a complete 1.0M character inventory remains byte-for-byte stable.
     */
    public function test_round_trips_all_108_character_inventory_slots(): void
    {
        $codec = new ItemCodec1_0M;
        $occupiedItem = hex2bin('00842001020304050000');

        self::assertIsString($occupiedItem);

        $inventory = $occupiedItem.str_repeat("\xFF", 107 * ItemCodec1_0M::SLOT_BYTES);
        $slots = $codec->decode($inventory, 108);

        self::assertCount(108, $slots);
        self::assertFalse($slots[0]->isEmpty());
        self::assertTrue($slots[107]->isEmpty());
        self::assertSame($inventory, $codec->encode($slots));
    }

    /**
     * Confirms malformed storage is rejected before partial decoding occurs.
     */
    public function test_rejects_a_blob_with_the_wrong_slot_count(): void
    {
        $codec = new ItemCodec1_0M;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected 1080 DarksTeam 1.0M item bytes, received 1070.');

        $codec->decode(str_repeat("\xFF", 1070), 108);
    }
}
