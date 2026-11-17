<?php

namespace Tests\Feature;

use App\Game\Contracts\ItemCodec;
use App\Game\Versions\MuDevsSeason21\ItemCodecAdapterMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemCodecMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemTransferRulesMuDevsSeason21;
use App\Game\Versions\MuEmuSeason6\ItemCodecMuEmuSeason6;
use App\Game\Versions\Version1_0M\ItemCodec1_0M;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies shared item features retain the complete native S21 item representation.
 */
class MuDevsSeason21SharedItemCodecTest extends TestCase
{
    /**
     * Matches a separately game-created third-generation wing and rejects dependent header state.
     */
    public function test_standard_wing_capture_matches_generation_and_rejects_unknown_state(): void
    {
        $captured = hex2bin('247FFF0000003D4000C000FFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        $codec = $this->app->make(ItemCodecAdapterMuDevsSeason21::class);
        $nativeCodec = $this->app->make(ItemCodecMuDevsSeason21::class);
        $rules = $this->app->make(ItemTransferRulesMuDevsSeason21::class);

        $generated = $codec->create(12, 36, 15, 255, 7, false, true, 0, 0, 61);
        self::assertSame($captured, $generated);
        self::assertTrue($rules->allowsTransfer($nativeCodec->decode($captured, 1)[0]));

        foreach ([8, 10, 11, 16, 24] as $position) {
            $changed = substr_replace($captured, "\x01", $position, 1);
            self::assertFalse($rules->allowsTransfer($nativeCodec->decode($changed, 1)[0]));
        }
    }

    /**
     * Carries client-matched identities, geometry and options through shared item slots.
     */
    public function test_client_items_round_trip_through_the_shared_storage_contract(): void
    {
        $fixtures = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-warehouse-items.json')), true, flags: JSON_THROW_ON_ERROR);
        $codec = $this->app->make(ItemCodecAdapterMuDevsSeason21::class);
        $binary = str_repeat("\xFF", 25 * 240);

        foreach ($fixtures as $fixture) {
            $binary = substr_replace($binary, hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        }

        $slots = $codec->decode($binary, 240);

        foreach ($fixtures as $fixture) {
            $slot = $slots[$fixture['slot']];
            $this->assertSame($fixture['code'], $slot->code());
            $this->assertSame($fixture['name'], $slot->name);
            $this->assertSame($fixture['width'], $slot->width);
            $this->assertSame($fixture['height'], $slot->height);
            $this->assertSame($fixture['socketOptions'], $slot->socketOptions);
            $this->assertSame(hex2bin($fixture['hex']), $slot->withSlot(3)->binary());
        }

        $this->assertTrue($slots[1]->isEmpty());
        $this->assertSame($binary, $codec->encode($slots));
    }

    /**
     * Compares common-header generation with ten independently client-captured records.
     *
     * These sanitized records predate the generator and cover high item indices,
     * ancient, Harmony, excellent, empty sockets and the zero socket bonus.
     */
    public function test_generation_matches_the_existing_client_capture_byte_for_byte(): void
    {
        $fixtures = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-warehouse-items.json')), true, flags: JSON_THROW_ON_ERROR);
        $codec = $this->app->make(ItemCodecAdapterMuDevsSeason21::class);

        foreach ($fixtures as $fixture) {
            $captured = hex2bin($fixture['hex']);
            $decoded = $codec->decode($captured, 1)[0];
            $serial = unpack('Nvalue', substr($captured, 3, 4))['value'];
            $generated = $codec->create(
                group: $decoded->group,
                index: $decoded->index,
                level: $decoded->level,
                durability: $decoded->durability,
                optionLevel: $decoded->optionLevel,
                hasSkill: $decoded->hasSkill,
                hasLuck: $decoded->hasLuck,
                excellentMask: $decoded->excellentMask,
                ancientOption: $decoded->ancientOption,
                serial: $serial,
                harmonyOption: $decoded->harmonyOption,
                socketBonus: $decoded->socketBonus,
                socketOptions: $decoded->socketOptions,
            );

            $this->assertSame($captured, $generated, $fixture['name']);
        }
    }

    /**
     * Matches the separately captured native Creation jewel without interpreting stack quantities.
     */
    public function test_native_creation_jewel_matches_its_saved_record(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-creation-jewel.json')), true, flags: JSON_THROW_ON_ERROR);
        $codec = $this->app->make(ItemCodecAdapterMuDevsSeason21::class);
        $binary = hex2bin($fixture['hex']);
        $item = $codec->decode($binary, 1)[0];

        $this->assertSame($fixture['code'], $item->code());
        $this->assertSame($fixture['name'], $item->name);
        $this->assertSame(1, $item->durability);
        $this->assertSame($binary, $codec->create(
            group: 14, index: 22, level: 0, durability: 1, optionLevel: 0,
            hasSkill: false, hasLuck: false, excellentMask: 0, ancientOption: 0, serial: 0x01020301,
        ));
    }

    /**
     * Refuses generation outside the verified common-header and catalog boundaries.
     *
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidGenerationOptions')]
    public function test_generation_rejects_unverified_or_overflowing_options(array $overrides): void
    {
        $options = array_replace([
            'group' => 0, 'index' => 0, 'level' => 0, 'durability' => 255,
            'optionLevel' => 0, 'hasSkill' => false, 'hasLuck' => false,
            'excellentMask' => 0, 'ancientOption' => 0, 'serial' => 123,
        ], $overrides);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->create(...$options);
    }

    /**
     * Covers unverified identities, header overflow and incompatible option storage.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidGenerationOptions(): iterable
    {
        yield 'unknown group layout' => [['group' => 16]];
        yield 'index overflow' => [['index' => 512]];
        yield 'level overflow' => [['level' => 16]];
        yield 'zero serial' => [['serial' => 0]];
        yield 'unverified 380 flag' => [['has380Option' => true]];
        yield 'socket on non-socket item' => [['socketOptions' => [254]]];
        yield 'too many sockets' => [['index' => 26, 'socketOptions' => [254, 254, 254, 254, 254, 254]]];
        yield 'Harmony on socket item' => [['index' => 26, 'harmonyOption' => 17]];
        yield 'extension flags are not ancient options' => [['ancientOption' => 64]];
    }

    /**
     * Preserves opaque extension bytes during shared slot movement and enrichment.
     */
    public function test_shared_slot_movement_preserves_unknown_extension_bytes(): void
    {
        $binary = hex2bin('0048FF0102030900000011FFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        $binary = substr_replace($binary, hex2bin('102030405060708090'), 16, 9);
        $codec = $this->app->make(ItemCodecAdapterMuDevsSeason21::class);
        $slot = $codec->decode($binary, 1)[0];

        $this->assertSame($binary, $codec->encode([$slot->withSlot(117)]));
        $this->assertArrayNotHasKey('serial', $slot->toArray());
        $this->assertArrayNotHasKey('nativeHex', $slot->toArray());
    }

    /**
     * Refuses unknown occupied headers instead of offering their cells for another item.
     */
    public function test_unsupported_identity_is_not_treated_as_an_empty_slot(): void
    {
        $binary = str_repeat("\x00", 25);
        $binary[9] = "\x01";

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->decode($binary, 1);
    }

    /**
     * Keeps unknown records occupied for display without exposing bytes or allowing custody.
     */
    public function test_unknown_display_slot_remains_occupied_and_round_trips_losslessly(): void
    {
        $binary = str_repeat("\x00", 25);
        $binary[9] = "\x01";
        $codec = $this->app->make(ItemCodecAdapterMuDevsSeason21::class);
        $slot = $codec->decodeForDisplay($binary, 1)[0]->withSlot(7);

        $this->assertFalse($slot->isEmpty());
        $this->assertFalse($slot->identityVerified);
        $this->assertFalse($slot->transferable);
        $this->assertSame('', $slot->code());
        $this->assertSame(__('ui.s21_warehouse.unknown_item'), $slot->name);
        $this->assertSame($binary, $codec->encode([$slot]));
        $this->assertArrayNotHasKey('serial', $slot->toArray());
        $this->assertArrayNotHasKey('nativeHex', $slot->toArray());
    }

    /**
     * Displays catalogued special equipment while refusing incomplete standalone custody.
     */
    public function test_pentagram_has_source_metadata_but_cannot_be_transferred(): void
    {
        $codec = $this->app->make(ItemCodecAdapterMuDevsSeason21::class);
        $binary = $codec->create(12, 200, 0, 255, 0, false, false, 0, 0, 123);
        $slot = $codec->decodeForDisplay($binary, 1)[0];

        $this->assertSame('12:200', $slot->code());
        $this->assertNotEmpty($slot->name);
        $this->assertTrue($slot->identityVerified);
        $this->assertFalse($slot->transferable);
    }

    /**
     * Keeps native S21 records out of each legacy storage encoder.
     *
     * @param  class-string<ItemCodec>  $codecClass
     */
    #[DataProvider('legacyCodecClasses')]
    public function test_legacy_encoders_reject_native_s21_slots(string $codecClass): void
    {
        $slot = $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->decode(str_repeat("\xFF", 25), 1)[0];

        $this->expectException(InvalidArgumentException::class);
        $this->app->make($codecClass)->encode([$slot]);
    }

    /**
     * Enumerates the three independently retained legacy storage formats.
     *
     * @return iterable<string, array{class-string<ItemCodec>}>
     */
    public static function legacyCodecClasses(): iterable
    {
        yield '97d' => [ItemCodec97d99i::class];
        yield '1.0M' => [ItemCodec1_0M::class];
        yield 'S6' => [ItemCodecMuEmuSeason6::class];
    }

    /**
     * Prevents a legacy storage record from being written into S21 storage.
     */
    public function test_encode_rejects_legacy_item_width(): void
    {
        $slot = $this->app->make(ItemCodecMuEmuSeason6::class)
            ->decode(str_repeat("\xFF", 16), 1)[0];

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->encode([$slot]);
    }
}
