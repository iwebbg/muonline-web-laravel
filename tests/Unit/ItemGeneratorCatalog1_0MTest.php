<?php

namespace Tests\Unit;

use App\Game\Versions\Version1_0M\ItemCatalog1_0M;
use App\Game\Versions\Version1_0M\ItemGeneratorCatalog1_0M;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the audited DarksTeam 1.0M item generator contract.
 */
final class ItemGeneratorCatalog1_0MTest extends TestCase
{
    /**
     * Confirms the generator exposes only the audited 1.0M equipment subset.
     */
    public function test_lists_audited_items_with_dark_lord_equipment(): void
    {
        $items = $this->catalog()->items();
        $itemsByCode = array_column($items, null, 'code');

        $this->assertCount(258, $items);
        $this->assertSame('Battle Scepter', $itemsByCode['2:8']['name']);
        $this->assertSame(54, $itemsByCode['2:8']['basePrice']);
        $this->assertSame('Cape of Lord', $itemsByCode['13:30']['name']);
        $this->assertSame(13, $itemsByCode['13:30']['maxLevel']);
        $this->assertSame(7, $itemsByCode['13:30']['maxOption']);
        $this->assertArrayNotHasKey('14:0', $itemsByCode);
    }

    /**
     * Confirms a maximum Cape of Lord quote uses the supplied webshop formula.
     */
    public function test_quotes_level_thirteen_and_option_stage_seven(): void
    {
        $quote = $this->catalog()->quote($this->capeOptions());

        $this->assertNotNull($quote);
        $this->assertSame(372, $quote->priceCredits);
        $this->assertSame([
            'base' => 180,
            'level' => 52,
            'additional_option' => 35,
            'skill' => 0,
            'luck' => 15,
            'excellent_options' => 90,
        ], $quote->priceBreakdown);
        $this->assertSame(13, $quote->itemSnapshot['level']);
        $this->assertSame(7, $quote->itemSnapshot['option_level']);
    }

    /**
     * Confirms values outside the 10-byte 1.0M format are rejected.
     *
     * @param  array{level?: int, option_level?: int, has_skill?: bool}  $overrides
     */
    #[DataProvider('unsupportedCapeOptions')]
    public function test_rejects_unsupported_cape_options(array $overrides): void
    {
        $options = array_replace($this->capeOptions(), $overrides);

        $this->assertNull($this->catalog()->quote($options));
    }

    /**
     * Confirms accessory restrictions come from the supplied legacy item data.
     */
    public function test_rejects_item_levels_for_ring_without_level_support(): void
    {
        $options = $this->capeOptions();
        $options['group'] = 13;
        $options['index'] = 8;
        $options['level'] = 1;
        $options['has_luck'] = false;

        $this->assertNull($this->catalog()->quote($options));
    }

    /**
     * Provides configurations that cannot be represented by the audited item.
     *
     * @return array<string, array{array{level?: int, option_level?: int, has_skill?: bool}}>
     */
    public static function unsupportedCapeOptions(): array
    {
        return [
            'level above thirteen' => [['level' => 14]],
            'option stage above seven' => [['option_level' => 8]],
            'skill unsupported by cape' => [['has_skill' => true]],
        ];
    }

    /**
     * Creates the catalog against the committed 1.0M fixtures.
     */
    private function catalog(): ItemGeneratorCatalog1_0M
    {
        $projectRoot = dirname(__DIR__, 2);
        $itemCatalog = new ItemCatalog1_0M($projectRoot.'/resources/game/1_0m/items.json');

        return new ItemGeneratorCatalog1_0M(
            $itemCatalog,
            $projectRoot.'/resources/game/1_0m/item-generator-pricing.json',
        );
    }

    /**
     * Returns one maximum-option Cape of Lord configuration.
     *
     * @return array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}
     */
    private function capeOptions(): array
    {
        return [
            'group' => 13,
            'index' => 30,
            'level' => 13,
            'durability' => 255,
            'option_level' => 7,
            'has_skill' => false,
            'has_luck' => true,
            'excellent_mask' => 63,
            'ancient_option' => 0,
        ];
    }
}
