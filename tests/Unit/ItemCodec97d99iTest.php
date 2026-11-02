<?php

namespace Tests\Unit;

use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the lossless Darksteam 97d+99i item codec.
 */
final class ItemCodec97d99iTest extends TestCase
{
    /**
     * Confirms decoded option fields and group extension bits from a known item.
     */
    public function test_it_decodes_a_known_ten_byte_item(): void
    {
        $codec = new ItemCodec97d99i;
        $binary = hex2bin('1F5B2AAABBCCDDC1037E');

        self::assertIsString($binary);

        $item = $codec->decode($binary, 1)[0];

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
     * Confirms empty slots remain identifiable without inventing item metadata.
     */
    public function test_it_decodes_an_empty_slot(): void
    {
        $codec = new ItemCodec97d99i;
        $item = $codec->decode(str_repeat("\xFF", 10), 1)[0];

        self::assertTrue($item->isEmpty());
        self::assertNull($item->group);
        self::assertNull($item->index);
        self::assertSame('', $item->code());
    }

    /**
     * Confirms decode and encode retain every byte in a mixed storage blob.
     */
    public function test_it_round_trips_a_blob_byte_for_byte(): void
    {
        $codec = new ItemCodec97d99i;
        $firstItem = hex2bin('00842001020304050000');
        $secondItem = hex2bin('1F5B2AAABBCCDDC1037E');

        self::assertIsString($firstItem);
        self::assertIsString($secondItem);

        $binary = $firstItem.str_repeat("\xFF", 20).$secondItem;
        $slots = $codec->decode($binary, 4);

        self::assertSame($binary, $codec->encode($slots));
    }

    /**
     * Confirms malformed blobs are rejected before any slot is decoded.
     */
    public function test_it_rejects_an_unexpected_blob_length(): void
    {
        $codec = new ItemCodec97d99i;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected 20 item bytes, received 10.');

        $codec->decode(str_repeat("\xFF", 10), 2);
    }
}
