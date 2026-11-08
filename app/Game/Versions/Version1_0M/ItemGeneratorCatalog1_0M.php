<?php

namespace App\Game\Versions\Version1_0M;

use App\Game\Contracts\ItemGeneratorCatalog;
use App\Shop\ItemGeneratorQuote;
use JsonException;
use RuntimeException;

/**
 * Provides the audited DarksTeam 1.0M generator catalog and webshop pricing.
 */
final class ItemGeneratorCatalog1_0M implements ItemGeneratorCatalog
{
    private const LEVEL_PRICE = 4;

    private const OPTION_PRICE = 5;

    private const SKILL_PRICE = 13;

    private const LUCK_PRICE = 15;

    private const EXCELLENT_OPTION_PRICE = 15;

    private string $pricingPath;

    /** @var array<string, array{base_price: int, allows_luck: bool, allows_skill: bool, allows_level: bool, allows_option: bool, max_option: int, max_level: int, max_excellent: int}>|null */
    private ?array $pricing = null;

    /**
     * Creates the catalog from the authoritative 1.0M item and pricing data.
     */
    public function __construct(
        private readonly ItemCatalog1_0M $itemCatalog,
        ?string $pricingPath = null,
    ) {
        $this->pricingPath = $pricingPath
            ?? resource_path('game/1_0m/item-generator-pricing.json');
    }

    /**
     * Returns every DarksTeam 1.0M item that has an audited positive price.
     *
     * @return list<array<string, int|string|bool|null>>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->itemCatalog->allDefinitions() as $definition) {
            $pricing = $this->pricing()[$definition->code()] ?? null;

            if (! is_array($pricing)) {
                continue;
            }

            $items[] = [
                'code' => $definition->code(),
                'name' => $definition->name,
                'image' => $definition->image,
                'width' => $definition->width,
                'height' => $definition->height,
                'excellentType' => $definition->excellentType,
                'optionType' => $definition->optionType,
                'basePrice' => $pricing['base_price'],
                'allowsLuck' => $pricing['allows_luck'],
                'allowsSkill' => $pricing['allows_skill'],
                'allowsLevel' => $pricing['allows_level'],
                'allowsOption' => $pricing['allows_option'],
                'maxOption' => $pricing['max_option'],
                'maxLevel' => $pricing['max_level'],
                'maxExcellent' => $pricing['max_excellent'],
            ];
        }

        return $items;
    }

    /**
     * Returns the audited webshop option prices used by client-side previews.
     *
     * @return array{level: int, additionalOption: int, skill: int, luck: int, excellentOption: int}
     */
    public function priceModifiers(): array
    {
        return [
            'level' => self::LEVEL_PRICE,
            'additionalOption' => self::OPTION_PRICE,
            'skill' => self::SKILL_PRICE,
            'luck' => self::LUCK_PRICE,
            'excellentOption' => self::EXCELLENT_OPTION_PRICE,
        ];
    }

    /**
     * Calculates a trusted 1.0M quote or rejects an unsupported configuration.
     *
     * @param  array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}  $options
     */
    public function quote(array $options): ?ItemGeneratorQuote
    {
        $definition = $this->itemCatalog->definitionFor($options['group'], $options['index']);

        if ($definition === null) {
            return null;
        }

        $pricing = $this->pricing()[$definition->code()] ?? null;

        if (! is_array($pricing) || ! $this->optionsAreAllowed($options, $pricing)) {
            return null;
        }

        $excellentCount = substr_count(decbin($options['excellent_mask']), '1');
        $breakdown = [
            'base' => $pricing['base_price'],
            'level' => $options['level'] * self::LEVEL_PRICE,
            'additional_option' => $options['option_level'] * self::OPTION_PRICE,
            'skill' => $options['has_skill'] ? self::SKILL_PRICE : 0,
            'luck' => $options['has_luck'] ? self::LUCK_PRICE : 0,
            'excellent_options' => $excellentCount * self::EXCELLENT_OPTION_PRICE,
        ];

        return new ItemGeneratorQuote(
            priceCredits: array_sum($breakdown),
            itemSnapshot: [
                'item_code' => $definition->code(),
                'item_name' => $definition->name,
                'item_image' => $definition->image,
                'item_width' => $definition->width,
                'item_height' => $definition->height,
                'level' => $options['level'],
                'durability' => $options['durability'],
                'option_level' => $options['option_level'],
                'has_skill' => $options['has_skill'],
                'has_luck' => $options['has_luck'],
                'excellent_mask' => $options['excellent_mask'],
                'ancient_option' => $options['ancient_option'],
                'weight' => 1,
            ],
            priceBreakdown: $breakdown,
        );
    }

    /**
     * Reports whether the selected item supports every requested binary option.
     *
     * @param  array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}  $options
     * @param  array{base_price: int, allows_luck: bool, allows_skill: bool, allows_level: bool, allows_option: bool, max_option: int, max_level: int, max_excellent: int}  $pricing
     */
    private function optionsAreAllowed(array $options, array $pricing): bool
    {
        $excellentCount = substr_count(decbin($options['excellent_mask']), '1');

        return $options['level'] >= 0
            && $options['level'] <= $pricing['max_level']
            && ($pricing['allows_level'] || $options['level'] === 0)
            && $options['durability'] >= 0
            && $options['durability'] <= 255
            && $options['option_level'] >= 0
            && $options['option_level'] <= $pricing['max_option']
            && ($pricing['allows_option'] || $options['option_level'] === 0)
            && ($pricing['allows_skill'] || ! $options['has_skill'])
            && ($pricing['allows_luck'] || ! $options['has_luck'])
            && $options['excellent_mask'] >= 0
            && $options['excellent_mask'] <= 63
            && $excellentCount <= $pricing['max_excellent']
            && $options['ancient_option'] === 0;
    }

    /**
     * Loads the audited prices imported from the supplied legacy webshop data.
     *
     * @return array<string, array{base_price: int, allows_luck: bool, allows_skill: bool, allows_level: bool, allows_option: bool, max_option: int, max_level: int, max_excellent: int}>
     *
     * @throws RuntimeException When the bundled pricing data is unavailable or invalid.
     */
    private function pricing(): array
    {
        if ($this->pricing !== null) {
            return $this->pricing;
        }

        $contents = file_get_contents($this->pricingPath);

        if ($contents === false) {
            throw new RuntimeException('The DarksTeam 1.0M item generator pricing could not be read.');
        }

        try {
            $pricing = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'The DarksTeam 1.0M item generator pricing is invalid.',
                previous: $exception,
            );
        }

        if (! is_array($pricing)) {
            throw new RuntimeException('The DarksTeam 1.0M item generator pricing must contain an object.');
        }

        /** @var array<string, array{base_price: int, allows_luck: bool, allows_skill: bool, allows_level: bool, allows_option: bool, max_option: int, max_level: int, max_excellent: int}> $pricing */
        $this->pricing = $pricing;

        return $this->pricing;
    }
}
