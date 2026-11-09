<?php

namespace App\Shop;

/**
 * Applies administrator pricing to one version-owned generated-item quote.
 */
final readonly class ItemGeneratorPriceAdjuster
{
    /**
     * Creates the price adjuster with the current economy policy reader.
     */
    public function __construct(
        private EconomySettingReader $economySettingReader,
    ) {}

    /**
     * Returns a server-authoritative quote with the configured markup recorded separately.
     */
    public function adjust(ItemGeneratorQuote $baseQuote): ItemGeneratorQuote
    {
        $settings = $this->economySettingReader->current();
        $markupCredits = $settings->generatedItemMarkup($baseQuote->priceCredits);

        return new ItemGeneratorQuote(
            priceCredits: $baseQuote->priceCredits + $markupCredits,
            itemSnapshot: $baseQuote->itemSnapshot,
            priceBreakdown: [
                ...$baseQuote->priceBreakdown,
                'markup' => $markupCredits,
            ],
        );
    }
}
