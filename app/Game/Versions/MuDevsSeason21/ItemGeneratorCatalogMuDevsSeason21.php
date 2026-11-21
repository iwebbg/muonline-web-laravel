<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Contracts\ItemGeneratorCatalog;
use App\Shop\ItemGeneratorQuote;
use RuntimeException;

/**
 * Prices reviewed native S21 equipment using fixed S6 references and explicit tier steps.
 */
final class ItemGeneratorCatalogMuDevsSeason21 implements ItemGeneratorCatalog
{
    private const OPTION_PRICES = [
        'level' => 4,
        'additionalOption' => 5,
        'skill' => 13,
        'luck' => 15,
        'excellentOption' => 15,
        'ancientFive' => 13,
        'ancientTen' => 20,
        'option380' => 15,
        'harmony' => 125,
        'socket' => 15,
    ];

    /** @var array<string, array{name: string, base_price: int, reference_code: string, tier_steps: int, source: string}>|null */
    private ?array $pricing = null;

    /**
     * Keeps native compatibility separate from the operator-owned price list.
     */
    public function __construct(
        private readonly ItemCatalogMuDevsSeason21 $catalog,
        private readonly ItemOptionsMuDevsSeason21 $itemOptions,
        private readonly ItemGrantRulesMuDevsSeason21 $rules,
        private readonly ItemTransferRulesMuDevsSeason21 $transferRules,
    ) {}

    /**
     * Returns only explicitly priced identities whose native construction is supported.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->pricing() as $code => $pricing) {
            [$group, $index] = array_map('intval', explode(':', $code));
            $metadata = $this->pricedDefinition($group, $index);

            if ($metadata === null) {
                continue;
            }

            $support = $this->itemOptions->definitionFor($group, $index);
            $advanced = $this->rules->optionsFor($group, $index);
            $ancientPrices = [];

            foreach ($advanced['ancientOptions'] as $ancientOption) {
                $ancientPrices[$ancientOption] = $this->ancientPrice($ancientOption);
            }

            $items[] = [
                'code' => $code,
                'name' => $metadata->name,
                'image' => $metadata->image,
                'width' => $metadata->width,
                'height' => $metadata->height,
                'excellentType' => 0,
                'optionType' => 0,
                'basePrice' => $pricing['base_price'],
                'allowsLuck' => $support['luck'],
                'allowsSkill' => $support['skill'],
                'allowsLevel' => true,
                'allowsOption' => $support['additional'],
                'maxOption' => $this->rules->maxOptionLevel(),
                'maxLevel' => $this->rules->maxItemLevel(),
                'maxExcellent' => count($support['excellent']),
                ...$advanced,
                'ancientPrices' => $ancientPrices,
                'nativeOptions' => [...$support, ...$advanced, 'constructionSupported' => true],
            ];
        }

        return $items;
    }

    /**
     * Reuses the established S6 option surcharges without reusing its item compatibility.
     *
     * @return array<string, int>
     */
    public function priceModifiers(): array
    {
        return self::OPTION_PRICES;
    }

    /**
     * Validates native bytes and calculates the authoritative purchase price on the server.
     *
     * @param  array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int, harmony_option?: int, socket_count?: int, has_380_option?: bool}  $options
     */
    public function quote(array $options): ?ItemGeneratorQuote
    {
        $metadata = $this->pricedDefinition($options['group'], $options['index']);

        if ($metadata === null || ! $this->rules->allowsConstruction($options)) {
            return null;
        }

        $support = $this->itemOptions->definitionFor($metadata->group, $metadata->index);
        $excellentCount = 0;

        foreach ($support['excellent'] as $option) {
            if (($options['excellent_mask'] & $option['bit']) !== 0) {
                $excellentCount++;
            }
        }

        $breakdown = [
            'base' => $this->pricing()[$metadata->code()]['base_price'],
            'level' => $options['level'] * self::OPTION_PRICES['level'],
            'additional_option' => $options['option_level'] * self::OPTION_PRICES['additionalOption'],
            'skill' => $options['has_skill'] ? self::OPTION_PRICES['skill'] : 0,
            'luck' => $options['has_luck'] ? self::OPTION_PRICES['luck'] : 0,
            'excellent_options' => $excellentCount * self::OPTION_PRICES['excellentOption'],
            'ancient' => $this->ancientPrice($options['ancient_option']),
            'option_380' => ($options['has_380_option'] ?? false) ? self::OPTION_PRICES['option380'] : 0,
            'harmony' => ($options['harmony_option'] ?? 0) > 0 ? self::OPTION_PRICES['harmony'] : 0,
            'sockets' => ($options['socket_count'] ?? 0) * self::OPTION_PRICES['socket'],
        ];

        return new ItemGeneratorQuote(
            priceCredits: array_sum($breakdown),
            itemSnapshot: [
                'item_code' => $metadata->code(),
                'item_name' => $metadata->name,
                'item_image' => $metadata->image,
                'item_width' => $metadata->width,
                'item_height' => $metadata->height,
                'level' => $options['level'],
                'durability' => $options['durability'],
                'option_level' => $options['option_level'],
                'has_skill' => $options['has_skill'],
                'has_luck' => $options['has_luck'],
                'excellent_mask' => $options['excellent_mask'],
                'ancient_option' => $options['ancient_option'],
                'harmony_option' => $options['harmony_option'] ?? 0,
                'socket_count' => $options['socket_count'] ?? 0,
                'has_380_option' => $options['has_380_option'] ?? false,
                'weight' => 1,
            ],
            priceBreakdown: $breakdown,
        );
    }

    /**
     * Refuses stale prices, renamed identities and native items requiring unsupported state.
     */
    private function pricedDefinition(int $group, int $index): ?ItemMetadataMuDevsSeason21
    {
        $pricing = $this->pricing()[$group.':'.$index] ?? null;
        $metadata = $this->catalog->definitionFor($group, $index);

        if ($metadata === null || $pricing === null || $metadata->name !== $pricing['name']
            || ! $this->transferRules->supportsIdentity($metadata)) {
            return null;
        }

        return $metadata;
    }

    /**
     * Charges the native ancient strength tier independently of its selected set variant.
     */
    private function ancientPrice(int $option): int
    {
        if ($option === 0) {
            return 0;
        }

        return ($option & 8) !== 0 ? self::OPTION_PRICES['ancientTen'] : self::OPTION_PRICES['ancientFive'];
    }

    /**
     * Loads the bounded, identity-keyed price snapshot once per catalog instance.
     *
     * @return array<string, array{name: string, base_price: int, reference_code: string, tier_steps: int, source: string}>
     */
    private function pricing(): array
    {
        if ($this->pricing !== null) {
            return $this->pricing;
        }

        $snapshot = json_decode(file_get_contents(resource_path('game/mudevs_season_21/item-generator-pricing.json')), true, flags: JSON_THROW_ON_ERROR);
        $items = $snapshot['items'] ?? null;

        if (! is_array($items) || $items === [] || array_is_list($items)) {
            throw new RuntimeException('The S21 generator requires an explicit item price snapshot.');
        }

        foreach ($items as $code => $pricing) {
            if (! is_string($code) || preg_match('/\A(?:[0-9]|1[0-5]):(?:0|[1-9][0-9]{0,2})\z/', $code) !== 1
                || ! is_array($pricing) || ! is_string($pricing['name'] ?? null)
                || ! is_int($pricing['base_price'] ?? null) || $pricing['base_price'] < 1) {
                throw new RuntimeException('The S21 generator price snapshot contains an invalid record.');
            }
        }

        return $this->pricing = $items;
    }
}
