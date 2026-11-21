<?php

namespace Tests\Unit;

use App\Game\Versions\MuDevsSeason21\ItemCatalogMuDevsSeason21;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies the native S21 catalog preserves source geometry and extended identities.
 */
final class ItemCatalogMuDevsSeason21Test extends TestCase
{
    /**
     * Loads every declared item while keeping unknown artwork explicitly absent.
     */
    public function test_loads_all_source_items_without_inventing_artwork(): void
    {
        $definitions = $this->catalog()->allDefinitions();
        $verifiedImages = json_decode(file_get_contents(__DIR__.'/../../resources/game/mudevs_season_21/item-images.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertCount(4701, $definitions);
        self::assertCount(20, array_unique(array_column($definitions, 'group')));
        foreach ($definitions as $definition) {
            self::assertGreaterThan(0, $definition->width);
            self::assertGreaterThan(0, $definition->height);
            self::assertSame($verifiedImages[$definition->code()] ?? null, $definition->image);
        }
    }

    /**
     * Assigns exact native model artwork and keeps absent client models on the shared fallback.
     */
    public function test_client_artwork_preserves_native_item_identity_and_missing_model_fallback(): void
    {
        $catalog = $this->catalog();

        self::assertSame('/images/items/mudevs_season_21/91cec7afd2cbe354.webp', $catalog->definitionFor(0, 0)->image);
        self::assertSame('/images/items/mudevs_season_21/31f919cf464e3d75.webp', $catalog->definitionFor(7, 270)->image);
        self::assertNull($catalog->definitionFor(13, 20)->image);
        self::assertNull($catalog->definitionFor(16, 284)->image);
    }

    /**
     * Verifies shipped artwork decodes within the export limits and matches its provenance hashes.
     */
    public function test_every_exported_model_has_a_valid_version_scoped_image_and_integrity_hash(): void
    {
        $root = dirname(__DIR__, 2);
        $provenance = json_decode(file_get_contents($root.'/resources/game/mudevs_season_21/item-image-provenance.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(4701, $provenance['catalogItemCount']);
        self::assertSame(4688, count($provenance['items']));
        self::assertCount(13, $provenance['missingItems']);
        foreach ($provenance['models'] as $model) {
            self::assertMatchesRegularExpression('~\A/images/items/mudevs_season_21/[a-f0-9]{16}\.webp\z~', $model['image']);
            $file = $root.'/public'.$model['image'];
            self::assertFileExists($file);
            self::assertSame($model['outputSha256'], hash_file('sha256', $file));
            $image = getimagesize($file);
            self::assertSame('image/webp', $image['mime']);
            self::assertGreaterThan(0, $image[0]);
            self::assertGreaterThan(0, $image[1]);
            self::assertLessThanOrEqual(256, $image[0]);
            self::assertLessThanOrEqual(256, $image[1]);
        }
    }

    /**
     * Keeps indices above one byte distinct from classic items with the same low byte.
     */
    public function test_preserves_high_indices_and_native_equipment_geometry(): void
    {
        $catalog = $this->catalog();
        $helmet = $catalog->definitionFor(7, 256);
        $emblem = $catalog->definitionFor(12, 510);

        self::assertNotNull($helmet);
        self::assertSame('7:256', $helmet->code());
        self::assertSame('Noble Pixie Helm', $helmet->name);
        self::assertSame(2, $helmet->width);
        self::assertSame(2, $helmet->height);
        self::assertSame(2, $helmet->equipmentSlot);
        self::assertSame('2', $helmet->sourceAttributes['LM']);
        self::assertNotNull($emblem);
        self::assertSame('Errtel Emblem', $emblem->name);
        self::assertSame(-1, $emblem->equipmentSlot);
        self::assertSame('Pinocchio', $catalog->definitionFor(16, 0)?->name);
        self::assertNotNull($catalog->definitionFor(21, 0));
        self::assertNull($catalog->definitionFor(15, 511));
    }

    /**
     * Retains all class, stacking and transfer attributes without reducing them to booleans.
     */
    public function test_preserves_native_class_requirements_and_operation_flags(): void
    {
        $kris = $this->catalog()->definitionFor(0, 0);

        self::assertNotNull($kris);
        self::assertSame('Kris', $kris->name);
        self::assertSame(1, $kris->width);
        self::assertSame(2, $kris->height);
        self::assertSame('0', $kris->sourceAttributes['GC']);
        self::assertSame('1', $kris->sourceAttributes['CS']);
        self::assertSame('40', $kris->sourceAttributes['RequireStrength']);
        self::assertSame('1', $kris->sourceAttributes['Transaction']);
        self::assertSame('1', $kris->sourceAttributes['StoreWarehouse']);
        self::assertSame('0', $kris->sourceAttributes['ItemOverlap']);
    }

    /**
     * Rejects corrupt snapshots rather than supplying default identities or cell sizes.
     */
    public function test_rejects_malformed_identity_and_geometry_metadata(): void
    {
        $path = tempnam(sys_get_temp_dir(), 's21-catalog-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, '{"7:256":{"Index":"0","Name":"Wrong item","Width":"0","Height":"2","Slot":"2"}}');

            $this->expectException(RuntimeException::class);
            (new ItemCatalogMuDevsSeason21($path))->allDefinitions();
        } finally {
            unlink($path);
        }
    }

    /**
     * Names the verified attack bonus only for its declared native item-section range.
     */
    public function test_socket_bonus_description_respects_item_sections_and_unknown_codes(): void
    {
        $catalog = $this->catalog();

        self::assertSame(['effect' => 'attack', 'displayValue' => '11'], $catalog->socketBonusFor(0, 0));
        self::assertSame(['effect' => 'attack', 'displayValue' => '11'], $catalog->socketBonusFor(4, 0));
        self::assertNull($catalog->socketBonusFor(5, 0));
        self::assertNull($catalog->socketBonusFor(-1, 0));
        self::assertNull($catalog->socketBonusFor(0, 1));
        self::assertNull($catalog->socketBonusFor(0, 255));
    }

    /**
     * Names the extended Fire capture only for its observed header and socket position.
     */
    public function test_extended_seed_description_requires_the_captured_context(): void
    {
        $catalog = $this->catalog();

        self::assertSame([
            'element' => 'fire', 'tier' => 7,
            'effect' => 'damage_wizardry_per_20_levels', 'displayValue' => '1.7',
        ], $catalog->socketSeedFor(46, 0, 64));
        self::assertNull($catalog->socketSeedFor(46, 0, 0));
        self::assertNull($catalog->socketSeedFor(46, 1, 64));
        self::assertNull($catalog->socketSeedFor(0, 0, 64));
        self::assertNull($catalog->socketSeedFor(0, 0, 128));
    }

    /**
     * Uses the bundled artifact without booting Laravel in a pure catalog test.
     */
    private function catalog(): ItemCatalogMuDevsSeason21
    {
        return new ItemCatalogMuDevsSeason21(__DIR__.'/../../resources/game/mudevs_season_21/items.json');
    }
}
