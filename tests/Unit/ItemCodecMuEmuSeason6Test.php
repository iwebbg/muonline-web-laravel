<?php

namespace Tests\Unit;

use App\Game\Data\ItemSlot;
use App\Game\Versions\MuEmuSeason6\ItemCodecMuEmuSeason6;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the MuEMU Season 6 codec against items exported from Update 32.
 */
final class ItemCodecMuEmuSeason6Test extends TestCase
{
    /**
     * Confirms the verified excellent Sword of Destruction option layout.
     */
    public function test_decodes_verified_excellent_item(): void
    {
        $item = $this->decodeHex('10EF54FFFFFE407F000000FFFFFFFFFF');

        $this->assertSame(0, $item->group);
        $this->assertSame(16, $item->index);
        $this->assertSame(13, $item->level);
        $this->assertSame(84, $item->durability);
        $this->assertSame(7, $item->optionLevel);
        $this->assertTrue($item->hasSkill);
        $this->assertTrue($item->hasLuck);
        $this->assertSame(63, $item->excellentMask);
        $this->assertSame('FFFFFE40', $item->serial);
    }

    /**
     * Confirms socket bonus and seed bytes remain distinct from Harmony.
     */
    public function test_decodes_verified_socket_item(): void
    {
        $item = $this->decodeHex('1AEF5AFFFFFE3F400000FFC9FE9BFFFF');

        $this->assertSame(0, $item->group);
        $this->assertSame(26, $item->index);
        $this->assertSame(255, $item->socketBonus);
        $this->assertSame([201, 254, 155, 255, 255], $item->socketOptions);
        $this->assertSame(0, $item->harmonyOption);
    }

    /**
     * Confirms Ancient and Harmony values decode from their independent bytes.
     */
    public function test_decodes_verified_ancient_and_harmony_items(): void
    {
        $ancient = $this->decodeHex('014C44FFFFFE3D400A8000FFFFFFFFFF');
        $harmony = $this->decodeHex('003F14FFFFFE3C0000005DFFFFFFFFFF');

        $this->assertSame(8, $ancient->group);
        $this->assertSame(1, $ancient->index);
        $this->assertSame(10, $ancient->ancientOption);
        $this->assertSame(93, $harmony->harmonyOption);
        $this->assertNull($harmony->socketBonus);
        $this->assertSame([], $harmony->socketOptions);
    }

    /**
     * Confirms an index above 31 remains valid for Rage Fighter equipment.
     */
    public function test_decodes_verified_rage_fighter_item(): void
    {
        $item = $this->decodeHex('20DD41FFFFFE3958000000FFFFFFFFFF');

        $this->assertSame(0, $item->group);
        $this->assertSame(32, $item->index);
        $this->assertSame(11, $item->level);
        $this->assertSame(24, $item->excellentMask);
    }

    /**
     * Confirms the low group nibble exposes the verified 380 option flag.
     */
    public function test_decodes_verified_380_item(): void
    {
        $item = $this->decodeHex('1D6F5AFFFFFE3840008800FFFFFFFFFF');

        $this->assertSame(8, $item->group);
        $this->assertSame(29, $item->index);
        $this->assertSame(13, $item->level);
        $this->assertSame(7, $item->optionLevel);
        $this->assertTrue($item->hasLuck);
        $this->assertTrue($item->has380Option);
    }

    /**
     * Confirms a full MuEMU warehouse round trip retains every native byte.
     */
    public function test_round_trips_full_warehouse_without_changing_bytes(): void
    {
        $records = [
            '10EF54FFFFFE407F000000FFFFFFFFFF',
            '1AEF5AFFFFFE3F400000FFC9FE9BFFFF',
            '065D28FFFFFE3E4600A000FFFFFFFFFF',
            str_repeat('FF', ItemCodecMuEmuSeason6::SLOT_BYTES),
            '014C44FFFFFE3D400A8000FFFFFFFFFF',
            str_repeat('FF', ItemCodecMuEmuSeason6::SLOT_BYTES),
            '003F14FFFFFE3C0000005DFFFFFFFFFF',
            '20DD41FFFFFE3958000000FFFFFFFFFF',
            '1D6F5AFFFFFE3840008800FFFFFFFFFF',
        ];
        $records = array_pad($records, 240, str_repeat('FF', ItemCodecMuEmuSeason6::SLOT_BYTES));
        $binary = hex2bin(implode('', $records));

        $this->assertIsString($binary);

        $codec = new ItemCodecMuEmuSeason6;
        $slots = $codec->decode($binary, 240);

        $this->assertCount(240, $slots);
        $this->assertSame($binary, $codec->encode($slots));
    }

    /**
     * Confirms newly generated basic items use the native sixteen-byte layout.
     */
    public function test_creates_basic_native_item(): void
    {
        $codec = new ItemCodecMuEmuSeason6;
        $binary = $codec->create(
            group: 8,
            index: 29,
            level: 13,
            durability: 90,
            optionLevel: 7,
            hasSkill: false,
            hasLuck: true,
            excellentMask: 0,
            ancientOption: 0,
            serial: 0x01020304,
        );
        $item = $codec->decode($binary, 1)[0];

        $this->assertSame(16, strlen($binary));
        $this->assertSame('1D6F5A0102030440008000FFFFFFFFFF', strtoupper(bin2hex($binary)));
        $this->assertSame(8, $item->group);
        $this->assertSame(29, $item->index);
        $this->assertFalse($item->has380Option);
    }

    /**
     * Confirms invalid native blob lengths fail before any partial decode.
     */
    public function test_rejects_invalid_blob_length(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected 16 MuEMU Season 6 item bytes, received 15.');

        (new ItemCodecMuEmuSeason6)->decode(str_repeat("\xFF", 15), 1);
    }

    /**
     * Decodes one hexadecimal golden item fixture.
     */
    private function decodeHex(string $hex): ItemSlot
    {
        $binary = hex2bin($hex);

        $this->assertIsString($binary);

        return (new ItemCodecMuEmuSeason6)->decode($binary, 1)[0];
    }
}
