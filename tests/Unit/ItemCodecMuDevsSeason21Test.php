<?php

namespace Tests\Unit;

use App\Game\Versions\MuDevsSeason21\ItemCatalogMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemCodecMuDevsSeason21;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies client-matched S21 records without accessing the live game database.
 */
final class ItemCodecMuDevsSeason21Test extends TestCase
{
    /**
     * Matches captured client items, including a high-index helmet, open sockets and ancient armor.
     */
    public function test_decodes_client_matched_items_and_round_trips_the_full_warehouse(): void
    {
        $fixtures = json_decode(file_get_contents(__DIR__.'/../Fixtures/SqlServer/mudevs-season-21-warehouse-items.json'), true, flags: JSON_THROW_ON_ERROR);
        $binary = str_repeat("\xFF", 6000);

        foreach ($fixtures as $fixture) {
            $binary = substr_replace($binary, hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        }

        $codec = $this->codec();
        $records = $codec->decode($binary, 240);

        self::assertCount(240, $records);
        self::assertSame($binary, $codec->encode($records));
        self::assertTrue($records[239]->isEmpty);
        self::assertNull($records[239]->code());
        foreach ($fixtures as $fixture) {
            $snapshot = $records[$fixture['slot']]->toInspectionArray();
            self::assertFalse($records[$fixture['slot']]->isEmpty);
            foreach ($fixture as $key => $value) {
                $snapshotKey = $key === 'hex' ? 'nativeHex' : $key;
                self::assertSame($value, $snapshot[$snapshotKey], $fixture['name'].' '.$snapshotKey);
            }
            self::assertFalse($snapshot['advancedOptionsVerified']);
        }
        self::assertSame(255, $records[66]->socketBonus);
        self::assertNull($records[75]->socketBonus);
        self::assertSame(5, $records[99]->ancientOption);
        self::assertSame("Warrior's", $records[99]->ancientSetName);
        self::assertSame(17, $records[110]->harmonyOption);
        self::assertNull($records[66]->harmonyOption);
        self::assertSame(0, $records[38]->socketBonus);
        self::assertNull($records[38]->harmonyOption);
    }

    /**
     * Retains non-default extension bytes without assigning guessed advanced options.
     */
    public function test_preserves_unverified_extension_and_option_bytes_exactly(): void
    {
        $binary = hex2bin('1FED010102030201000001FFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        $binary[16] = "\x12";
        $binary[24] = "\xAB";
        $codec = $this->codec();

        $records = $codec->decode($binary, 1);

        self::assertSame($binary, $codec->encode($records));
        self::assertFalse($records[0]->toInspectionArray()['advancedOptionsVerified']);
    }

    /**
     * Keeps an occupied unknown group header instead of mislabeling it as a classic item.
     */
    public function test_retains_unsupported_group_flags_without_inventing_an_identity(): void
    {
        $binary = hex2bin('0E7C010102030180007100FFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        $codec = $this->codec();

        $records = $codec->decode($binary, 1);

        self::assertFalse($records[0]->isEmpty);
        self::assertNull($records[0]->code());
        self::assertNull($records[0]->metadata);
        self::assertSame($binary, $codec->encode($records));
    }

    /**
     * Keeps unsupported ancient flags raw rather than inventing a set for a non-set item.
     */
    public function test_does_not_invent_an_ancient_set_for_an_unsupported_item(): void
    {
        $binary = hex2bin('004FFF010203077F050000FFFFFFFFFFFFFFFFFFFFFFFFFFFF');

        $records = $this->codec()->decode($binary, 1);

        self::assertSame(5, $records[0]->ancientOption);
        self::assertNull($records[0]->ancientSetName);
        self::assertNull($records[0]->ancientSetIndex);
        self::assertSame($binary, $this->codec()->encode($records));
    }

    /**
     * Rejects old-format records rather than treating them as native S21 storage.
     */
    public function test_rejects_a_sixteen_byte_legacy_record(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->codec()->decode(str_repeat("\xFF", 16), 1);
    }

    /**
     * Rejects a truncated final record rather than silently dropping it.
     */
    public function test_rejects_a_truncated_warehouse_blob(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->codec()->decode(str_repeat("\xFF", 5999), 240);
    }

    /**
     * Refuses to serialize an occupied-only subset at the wrong native positions.
     */
    public function test_rejects_reordered_storage_records(): void
    {
        $codec = $this->codec();
        $records = $codec->decode(str_repeat("\xFF", 50), 2);
        $this->expectException(InvalidArgumentException::class);

        $codec->encode(array_reverse($records));
    }

    /**
     * Supplies explicit artifacts without booting Laravel for binary-domain tests.
     */
    private function codec(): ItemCodecMuDevsSeason21
    {
        return new ItemCodecMuDevsSeason21(new ItemCatalogMuDevsSeason21(__DIR__.'/../../resources/game/mudevs_season_21/items.json'));
    }
}
