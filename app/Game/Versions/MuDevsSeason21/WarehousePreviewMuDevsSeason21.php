<?php

namespace App\Game\Versions\MuDevsSeason21;

/**
 * Presents native S21 records with exact stored geometry and explicitly verified fields.
 *
 * @phpstan-type PreviewItem array{slot: int, title: string, image: string|null, style: string, rarity: string, details: list<array{label: string, value: string}>}
 */
final class WarehousePreviewMuDevsSeason21
{
    /**
     * Supplies native metadata for explicitly client-matched socket descriptions.
     */
    public function __construct(
        private readonly ItemCatalogMuDevsSeason21 $catalog,
        private readonly ItemOptionsMuDevsSeason21 $options,
    ) {}

    /**
     * Builds occupied-item previews without exposing serials or raw native bytes.
     *
     * @param  list<ItemRecordMuDevsSeason21>  $records
     * @return list<PreviewItem>
     */
    public function items(array $records): array
    {
        $items = [];

        foreach ($records as $record) {
            if (! $record->isEmpty) {
                $items[] = $this->item($record);
            }
        }

        return $items;
    }

    /**
     * Combines source-backed identity with a read-only native-position rectangle.
     *
     * @return PreviewItem
     */
    private function item(ItemRecordMuDevsSeason21 $record): array
    {
        $name = $record->metadata?->name ?? __('ui.s21_warehouse.unknown_item');
        $rarity = 'normal';

        if ($record->ancientSetName !== null) {
            $name = $record->ancientSetName.' '.$name;
            $rarity = 'ancient';
        } elseif ($record->excellentMask > 0) {
            $name = __('ui.s21_warehouse.excellent').' '.$name;
            $rarity = 'excellent';
        } elseif (($record->level ?? 0) >= 9 || $record->hasLuck) {
            $rarity = 'rare';
        }

        if (($record->level ?? 0) > 0) {
            $name .= ' +'.$record->level;
        }

        $width = $record->metadata?->width ?? 1;
        $height = $record->metadata?->height ?? 1;
        $style = sprintf(
            'left: calc(var(--inventory-cell-size) * %d + 0.18rem); top: calc(var(--inventory-cell-size) * %d + 0.18rem); width: calc(var(--inventory-cell-size) * %d - 0.36rem); height: calc(var(--inventory-cell-size) * %d - 0.36rem);',
            $record->slot % 8,
            intdiv($record->slot, 8),
            $width,
            $height,
        );

        return [
            'slot' => $record->slot,
            'title' => $name,
            'image' => $record->metadata?->image,
            'style' => $style,
            'rarity' => $rarity,
            'details' => $this->details($record),
        ];
    }

    /**
     * Lists known fields without guessing game formulas or unverified option names.
     *
     * @return list<array{label: string, value: string}>
     */
    private function details(ItemRecordMuDevsSeason21 $record): array
    {
        $fields = [
            'code' => $record->code() ?? __('ui.s21_warehouse.unknown'),
            'slot' => (string) $record->slot,
            'size' => $record->metadata === null ? __('ui.s21_warehouse.unknown') : $record->metadata->width.'×'.$record->metadata->height,
            'durability' => (string) $record->durability,
            'skill' => __('ui.s21_warehouse.'.($record->hasSkill ? 'yes' : 'no')),
            'luck' => __('ui.s21_warehouse.'.($record->hasLuck ? 'yes' : 'no')),
            'option' => (string) $record->optionLevel,
            'excellent_mask' => (string) $record->excellentMask,
        ];

        if ($record->ancientOption > 0) {
            $fields['ancient_set'] = $record->ancientSetName ?? __('ui.s21_warehouse.unknown');
            $fields['ancient_byte'] = (string) $record->ancientOption;
        }

        if (($record->harmonyOption ?? 0) > 0) {
            $fields['harmony_byte'] = (string) $record->harmonyOption;
        }

        if ($record->socketExtensionFlags !== 0) {
            $fields['socket_header_flags'] = (string) $record->socketExtensionFlags;
        }

        $details = [];

        foreach ($fields as $key => $value) {
            $details[] = ['label' => __('ui.s21_warehouse.'.$key), 'value' => $value];
        }

        $nativeOptions = $this->options->definitionFor($record->group ?? -1, $record->index ?? -1);

        if ($record->optionLevel > 0 && $nativeOptions['additionalLabel'] !== null) {
            $details[] = [
                'label' => __('ui.s21_warehouse.option'),
                'value' => str_replace('{value}', (string) ($record->optionLevel * $nativeOptions['additionalValue']), $nativeOptions['additionalLabel']),
            ];
        }

        foreach ($nativeOptions['excellent'] as $option) {
            if (($record->excellentMask & $option['bit']) !== 0) {
                $details[] = ['label' => __('ui.s21_warehouse.excellent'), 'value' => $option['label']];
            }
        }

        foreach ($record->socketOptions as $position => $value) {
            if ($value !== 255) {
                $details[] = [
                    'label' => __('ui.s21_warehouse.socket', ['number' => $position + 1]),
                    'value' => $this->socketValue($value, $position, $record),
                ];
            }
        }

        if ($record->socketBonus !== null && $record->socketBonus !== 255) {
            $details[] = ['label' => __('ui.s21_warehouse.socket_bonus'), 'value' => (string) $record->socketBonus];
            $bonus = $record->group === null ? null : $this->catalog->socketBonusFor($record->group, $record->socketBonus);

            if ($bonus !== null) {
                $details[] = [
                    'label' => __('ui.s21_warehouse.socket_bonus_effect'),
                    'value' => __('ui.s21_warehouse.socket_bonus_summary', [
                        'effect' => __('ui.s21_warehouse.bonus_'.$bonus['effect']),
                        'value' => $bonus['displayValue'],
                    ]),
                ];
            }
        }

        return $details;
    }

    /**
     * Describes empty or verified filled sockets without guessing unsupported seed encodings.
     */
    private function socketValue(int $value, int $position, ItemRecordMuDevsSeason21 $record): string
    {
        if ($value === 254) {
            return __('ui.s21_warehouse.empty_socket');
        }

        $seed = null;

        if (substr($record->binary(), 16) === str_repeat("\xFF", 9)) {
            $seed = $this->catalog->socketSeedFor($value, $position, $record->socketExtensionFlags);
        }

        if ($seed === null) {
            return __('ui.s21_warehouse.seed_code', ['value' => $value]);
        }

        return __('ui.s21_warehouse.seed_summary', [
            'level' => $seed['tier'],
            'element' => __('ui.s21_warehouse.element_'.$seed['element']),
            'effect' => __('ui.s21_warehouse.effect_'.$seed['effect']),
            'value' => $seed['displayValue'],
        ]);
    }
}
