<?php

namespace App\CreditEarning;

use App\Game\Contracts\ItemCatalog;
use App\Http\AccountPanelConfiguration;
use App\Models\CreditExchangeOffer;

/**
 * Presents exact exchange signatures with the same option labels as item tooltips.
 */
final readonly class CreditExchangeOfferPresenter
{
    /**
     * Creates the presenter with the shared bilingual item labels.
     */
    public function __construct(
        private AccountPanelConfiguration $accountPanelConfiguration,
        private ItemCatalog $itemCatalog,
    ) {}

    /**
     * Returns one offer ready for account and administrator views.
     *
     * @return array{title: string, options: list<string>, image: string|null}
     */
    public function present(CreditExchangeOffer $offer): array
    {
        $labels = $this->accountPanelConfiguration->itemOptionLabels();
        $native = $this->itemCatalog->definitionFor($offer->item_group, $offer->item_index)?->nativeOptions;
        $prefix = $offer->excellent_mask > 0 && $offer->excellent_type !== 4
            ? $labels['excellentPrefix'].' '
            : '';
        $level = $offer->level > 0 ? ' +'.$offer->level : '';
        $options = [];

        if ($offer->has_skill) {
            $options[] = $labels['skill'];
        }

        if ($offer->has_luck) {
            $options[] = $offer->excellent_type === 1
                ? $labels['luckSoul']
                : $labels['luckCritical'];
        }

        if ($offer->option_level > 0) {
            $multiplier = match ($offer->option_type) {
                1 => 1,
                2 => 5,
                default => 4,
            };
            $suffix = $offer->option_type === 1 ? '%' : '';
            $options[] = $native === null
                ? '+'.($offer->option_level * $multiplier).$suffix
                : str_replace('{value}', (string) ($offer->option_level * $native['additionalValue']), $native['additionalLabel'] ?? '');
        }

        $excellentLabels = match ($offer->excellent_type) {
            1 => $labels['excellent']['armor'],
            4 => $labels['excellent']['wing'],
            default => $labels['excellent']['weapon'],
        };

        if ($native === null) {
            foreach ($excellentLabels as $index => $excellentLabel) {
                if ($excellentLabel !== '' && ($offer->excellent_mask & (1 << $index)) !== 0) {
                    $options[] = $excellentLabel;
                }
            }
        } else {
            foreach ($native['excellent'] as $option) {
                if (($offer->excellent_mask & $option['bit']) !== 0) {
                    $options[] = $option['label'];
                }
            }
        }

        if ($offer->ancient_option > 0) {
            $options[] = $labels['ancientOption'].' +'.$offer->ancient_option;
        }

        return [
            'title' => $prefix.$offer->item_name_snapshot.$level,
            'options' => $options,
            'image' => $offer->item_image_snapshot,
        ];
    }
}
