<?php

namespace Tests\Unit;

use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the shared Darksteam 97d+99i item catalog.
 */
final class ItemCatalog97d99iTest extends TestCase
{
    /**
     * Confirms a standard item exposes its exact name, dimensions and image.
     */
    public function test_it_returns_a_known_item_definition(): void
    {
        $catalog = $this->catalog();

        $definition = $catalog->definitionFor(0, 0);

        self::assertNotNull($definition);
        self::assertSame('Kris', $definition->name);
        self::assertSame(1, $definition->width);
        self::assertSame(2, $definition->height);
        self::assertSame('/images/items/97d_99i/0000.gif', $definition->image);
    }

    /**
     * Confirms catalog items without an available legacy image retain their dimensions.
     */
    public function test_it_returns_a_definition_without_an_image_when_the_asset_is_missing(): void
    {
        $catalog = $this->catalog();

        $definition = $catalog->definitionFor(12, 7);

        self::assertNotNull($definition);
        self::assertSame('Twisting Slash', $definition->name);
        self::assertSame(1, $definition->width);
        self::assertSame(1, $definition->height);
        self::assertNull($definition->image);
    }

    /**
     * Confirms an unknown group and index do not receive invented metadata.
     */
    public function test_it_returns_null_for_an_unknown_item(): void
    {
        $catalog = $this->catalog();

        self::assertNull($catalog->definitionFor(99, 99));
    }

    /**
     * Creates the catalog against the committed version metadata fixture.
     */
    private function catalog(): ItemCatalog97d99i
    {
        $catalogPath = dirname(__DIR__, 2).'/resources/game/97d99i/items.json';

        return new ItemCatalog97d99i($catalogPath);
    }
}
