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
     * Reads the sphere tier from native item identity without confusing it with item upgrade level.
     */
    public function test_reads_a_client_matched_fire_seed_sphere_without_weapon_options(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/SqlServer/mudevs-season-21-fire-seed-sphere.json'), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $codec = $this->codec();

        $records = $codec->decode($binary, 240);
        $sphere = $records[3];

        self::assertSame('12:100', $sphere->code());
        self::assertSame('Seed Sphere [Fire][Lvl. 1]', $sphere->metadata?->name);
        self::assertSame(0, $sphere->level);
        self::assertSame(1, $sphere->durability);
        self::assertSame(1, $sphere->metadata?->width);
        self::assertSame(1, $sphere->metadata?->height);
        self::assertNull($sphere->harmonyOption);
        self::assertNull($sphere->socketBonus);
        self::assertSame([], $sphere->socketOptions);
        self::assertSame($binary, $codec->encode($records));
    }

    /**
     * Retains a valid zero-valued seed alongside empty and absent socket positions.
     */
    public function test_preserves_a_client_matched_zero_seed_without_confusing_socket_sentinels(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/SqlServer/mudevs-season-21-flameberge-fire-socket.json'), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $codec = $this->codec();

        $records = $codec->decode($binary, 240);
        $weapon = $records[3];

        self::assertFalse($weapon->isEmpty);
        self::assertSame('0:26', $weapon->code());
        self::assertSame([0, 254, 254, 255, 255], $weapon->socketOptions);
        self::assertSame(255, $weapon->socketBonus);
        self::assertNull($weapon->harmonyOption);
        self::assertSame($binary, $codec->encode($records));
    }

    /**
     * Preserves an existing zero-valued Fire seed when the second socket gains Lightning.
     */
    public function test_retains_both_client_matched_fire_and_lightning_socket_positions(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/SqlServer/mudevs-season-21-flameberge-fire-lightning-sockets.json'), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $codec = $this->codec();

        $records = $codec->decode($binary, 240);

        self::assertSame('0:26', $records[19]->code());
        self::assertSame([0, 29, 254, 255, 255], $records[19]->socketOptions);
        self::assertSame(255, $records[19]->socketBonus);
        self::assertNull($records[19]->harmonyOption);
        self::assertSame($binary, $codec->encode($records));
    }

    /**
     * Retains three occupied sockets and a valid zero bonus after the third client insertion.
     */
    public function test_preserves_fire_lightning_ice_and_the_zero_bonus(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/SqlServer/mudevs-season-21-flameberge-three-seeds-bonus.json'), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $codec = $this->codec();

        $records = $codec->decode($binary, 240);

        self::assertSame([0, 29, 16, 255, 255], $records[2]->socketOptions);
        self::assertSame(0, $records[2]->socketBonus);
        self::assertNull($records[2]->harmonyOption);
        self::assertSame($binary, $codec->encode($records));
    }

    /**
     * Separates the captured tier-seven socket header flag from ancient state without changing bytes.
     */
    public function test_tier_seven_socket_flag_does_not_create_a_false_ancient_item(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/SqlServer/mudevs-season-21-cyclone-fire-tier-seven.json'), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $codec = $this->codec();

        $records = $codec->decode($binary, 240);
        $weapon = $records[3];

        self::assertSame('0:36', $weapon->code());
        self::assertSame([46, 254, 254, 255, 255], $weapon->socketOptions);
        self::assertSame(64, $weapon->socketExtensionFlags);
        self::assertSame(0, $weapon->ancientOption);
        self::assertNull($weapon->ancientSetName);
        self::assertNull($weapon->ancientSetIndex);
        self::assertSame(64, ord($weapon->binary()[8]));
        self::assertSame($binary, $codec->encode($records));
    }

    /**
     * Supplies explicit artifacts without booting Laravel for binary-domain tests.
     */
    private function codec(): ItemCodecMuDevsSeason21
    {
        return new ItemCodecMuDevsSeason21(new ItemCatalogMuDevsSeason21(__DIR__.'/../../resources/game/mudevs_season_21/items.json'));
    }
}
