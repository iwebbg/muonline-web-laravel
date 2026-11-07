<?php

namespace Tests\Unit;

use App\Game\Versions\Version1_0M\ItemCatalog1_0M;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the catalog generated from the DarksTeam 1.0M server item list.
 */
final class ItemCatalog1_0MTest extends TestCase
{
    /**
     * Confirms every authoritative server item is represented exactly once.
     */
    public function test_lists_all_authoritative_server_items(): void
    {
        $definitions = $this->catalog()->allDefinitions();

        $this->assertCount(354, $definitions);
        $this->assertSame('0:0', $definitions[0]->code());
        $this->assertSame('Kris', $definitions[0]->name);
    }

    /**
     * Confirms a 1.0M Dark Lord weapon retains its native identity and dimensions.
     */
    public function test_returns_dark_lord_scepter_metadata(): void
    {
        $definition = $this->catalog()->definitionFor(2, 8);

        $this->assertNotNull($definition);
        $this->assertSame('Battle Scepter', $definition->name);
        $this->assertSame(1, $definition->width);
        $this->assertSame(3, $definition->height);
        $this->assertSame('/images/items/1_0m/2008.gif', $definition->image);
    }

    /**
     * Confirms DarksTeam names take precedence over conflicting market labels.
     */
    public function test_uses_server_name_for_conflicting_market_metadata(): void
    {
        $definition = $this->catalog()->definitionFor(0, 31);

        $this->assertNotNull($definition);
        $this->assertSame('Death Broad', $definition->name);
    }

    /**
     * Confirms missing legacy artwork does not remove valid server items.
     */
    public function test_keeps_items_without_matching_artwork(): void
    {
        $definition = $this->catalog()->definitionFor(13, 30);

        $this->assertNotNull($definition);
        $this->assertSame('Cape of Lord', $definition->name);
        $this->assertNull($definition->image);
    }

    /**
     * Confirms an identity absent from the server list is never invented.
     */
    public function test_returns_null_for_unknown_item(): void
    {
        $this->assertNull($this->catalog()->definitionFor(16, 0));
    }

    /**
     * Creates the catalog against the committed 1.0M metadata fixture.
     */
    private function catalog(): ItemCatalog1_0M
    {
        $catalogPath = dirname(__DIR__, 2).'/resources/game/1_0m/items.json';

        return new ItemCatalog1_0M($catalogPath);
    }
}
