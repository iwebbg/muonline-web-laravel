<?php

namespace Tests\Unit;

use App\Game\Versions\MuEmuSeason6\ItemCatalogMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\ItemGeneratorCatalogMuEmuSeason6;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the explicitly priced MuEMU Season 6 item generator subset.
 */
final class ItemGeneratorCatalogMuEmuSeason6Test extends TestCase
{
    /**
     * Confirms the generator exposes only items priced by the supplied webshop.
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
        $this->assertArrayNotHasKey('0:32', $itemsByCode);
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
     * Confirms values outside the audited webshop rules are rejected.
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
     * Creates the generator against the committed MuEMU fixtures.
     */
    private function catalog(): ItemGeneratorCatalogMuEmuSeason6
    {
        $projectRoot = dirname(__DIR__, 2);
        $itemCatalog = new ItemCatalogMuEmuSeason6(
            $projectRoot.'/resources/game/muemu_season_6/items.json',
        );

        return new ItemGeneratorCatalogMuEmuSeason6(
            $itemCatalog,
            $projectRoot.'/resources/game/muemu_season_6/item-generator-pricing.json',
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
