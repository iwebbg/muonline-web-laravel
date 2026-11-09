<?php

namespace Tests\Unit;

use App\Game\Versions\MuEmuSeason6\ItemCatalogMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\ItemGrantRulesMuEmuSeason6;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the catalog imported from MuEMU Season 6 Update 32 server data.
 */
final class ItemCatalogMuEmuSeason6Test extends TestCase
{
    /**
     * Confirms every authoritative server item is represented exactly once.
     */
    public function test_lists_all_authoritative_server_items(): void
    {
        $definitions = $this->catalog()->allDefinitions();

        self::assertCount(938, $definitions);
        self::assertSame('0:0', $definitions[0]->code());
        self::assertSame('Kris', $definitions[0]->name);
        self::assertSame('15:36', $definitions[937]->code());
        self::assertSame('Defense Rate Parchment', $definitions[937]->name);
    }

    /**
     * Confirms the Rage Fighter weapon missing from legacy catalogs is available.
     */
    public function test_returns_rage_fighter_item_metadata(): void
    {
        $definition = $this->catalog()->definitionFor(0, 32);

        self::assertNotNull($definition);
        self::assertSame('Sacred Glove', $definition->name);
        self::assertSame(1, $definition->width);
        self::assertSame(2, $definition->height);
        self::assertSame('/images/items/muemu_season_6/0-32.jpg', $definition->image);
        self::assertFileExists(dirname(__DIR__, 2).'/public'.$definition->image);
    }

    /**
     * Confirms armor, shields, wings and accessories expose suitable option labels.
     */
    public function test_returns_version_specific_option_types(): void
    {
        $shield = $this->catalog()->definitionFor(6, 0);
        $armor = $this->catalog()->definitionFor(8, 29);
        $wing = $this->catalog()->definitionFor(12, 36);
        $ring = $this->catalog()->definitionFor(13, 8);

        self::assertNotNull($shield);
        self::assertSame(1, $shield->excellentType);
        self::assertSame(2, $shield->optionType);
        self::assertNotNull($armor);
        self::assertSame(1, $armor->excellentType);
        self::assertSame(0, $armor->optionType);
        self::assertNotNull($wing);
        self::assertSame(4, $wing->excellentType);
        self::assertNotNull($ring);
        self::assertSame(1, $ring->excellentType);
        self::assertSame(1, $ring->optionType);
    }

    /**
     * Confirms catalog entries remain usable when the supplied editor lacks artwork.
     */
    public function test_keeps_items_without_matching_editor_artwork(): void
    {
        $definition = $this->catalog()->definitionFor(12, 71);

        self::assertNotNull($definition);
        self::assertSame('Sphere (Di)', $definition->name);
        self::assertNull($definition->image);
    }

    /**
     * Confirms identities absent from the server item list are never invented.
     */
    public function test_returns_null_for_unknown_or_editor_only_items(): void
    {
        self::assertNull($this->catalog()->definitionFor(0, 36));
        self::assertNull($this->catalog()->definitionFor(16, 0));
    }

    /**
     * Confirms the Update 32 codec accepts its native level and option ranges.
     */
    public function test_exposes_native_item_option_limits(): void
    {
        $rules = new ItemGrantRulesMuEmuSeason6;

        self::assertSame(15, $rules->maxItemLevel());
        self::assertSame(range(0, 15), $rules->itemLevels());
        self::assertSame(7, $rules->maxOptionLevel());
        self::assertSame(range(0, 7), $rules->optionLevels());
    }

    /**
     * Creates the catalog against the committed MuEMU metadata fixture.
     */
    private function catalog(): ItemCatalogMuEmuSeason6
    {
        $catalogPath = dirname(__DIR__, 2).'/resources/game/muemu_season_6/items.json';

        return new ItemCatalogMuEmuSeason6($catalogPath);
    }
}
