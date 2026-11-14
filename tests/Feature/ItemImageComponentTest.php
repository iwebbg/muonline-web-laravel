<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Verifies item artwork has a shared fallback without changing item layout or identity.
 */
final class ItemImageComponentTest extends TestCase
{
    /**
     * Shows the placeholder immediately when an adapter has no artwork path.
     */
    public function test_missing_and_empty_artwork_render_the_default_image(): void
    {
        foreach ([null, ''] as $source) {
            $html = Blade::render('<x-item-image :src="$source" alt="Kris" class="size-16" />', ['source' => $source]);

            $this->assertStringContainsString('src="http://localhost/images/items/unknown-item.svg"', $html);
            $this->assertStringContainsString('alt="Kris"', $html);
            $this->assertStringContainsString('object-contain size-16', $html);
        }
    }

    /**
     * Preserves known artwork and escapes the item name in the image label.
     */
    public function test_existing_artwork_and_escaped_name_are_preserved(): void
    {
        $html = Blade::render('<x-item-image :src="$source" :alt="$name" />', [
            'source' => '/images/items/97d_99i/0000.gif',
            'name' => 'Excellent <Blade>',
        ]);

        $this->assertStringContainsString('src="/images/items/97d_99i/0000.gif"', $html);
        $this->assertStringContainsString('alt="Excellent &lt;Blade&gt;"', $html);
    }

    /**
     * Keeps artwork reactive while guarding errors if the fallback itself fails.
     */
    public function test_dynamic_images_include_missing_path_and_load_error_fallbacks(): void
    {
        $html = Blade::render('<x-item-image source-expression="item.image" label-expression="item.name" />');

        $this->assertStringContainsString('x-bind:src="(item.image) || $el.dataset.fallbackSrc"', $html);
        $this->assertStringContainsString('x-bind:alt="item.name"', $html);
        $this->assertStringContainsString('$el.complete && $el.naturalWidth === 0', $html);
        $this->assertStringContainsString("if (\$el.getAttribute('src') !== \$el.dataset.fallbackSrc) { \$el.src = \$el.dataset.fallbackSrc; }", $html);
    }

    /**
     * Retains grid sizing and tooltips while replacing the code-only empty preview.
     */
    public function test_inventory_grid_keeps_item_dimensions_and_tooltips_with_the_placeholder(): void
    {
        $html = Blade::render('<x-item-grid :slots="120" items="vault.items" />');

        $this->assertStringContainsString('itemGridStyle(item, 8, true)', $html);
        $this->assertStringContainsString('inventory-item-image', $html);
        $this->assertStringContainsString('images/items/unknown-item.svg', $html);
        $this->assertStringContainsString('item-tooltip', $html);
        $this->assertStringNotContainsString('x-text="item.code"', $html);
    }
}
