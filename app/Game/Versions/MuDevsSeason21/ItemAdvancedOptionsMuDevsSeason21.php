<?php

namespace App\Game\Versions\MuDevsSeason21;

use Illuminate\Container\Attributes\Scoped;

/**
 * Shares source-backed native advanced choices without depending on encoders or delivery services.
 */
#[Scoped]
final class ItemAdvancedOptionsMuDevsSeason21
{
    /** @var array{items: array<string, array{type: string, minimumItemLevel: int}>, effects: array<string, list<array{value: int, label: string, minimumLevel: int, values: list<int>}>>}|null */
    private ?array $harmony = null;

    /**
     * Uses the native ancient and socket catalogs as compatibility authorities.
     */
    public function __construct(private readonly ItemCatalogMuDevsSeason21 $catalog) {}

    /**
     * Returns source-declared ancient sets, native Harmony families and verified empty socket counts.
     *
     * @return array{ancientOptions: list<int>, ancientOptionLabels: array<int, string>, allows380: bool, harmonyType: ?string, harmonyOptions: list<array{value: int, minimumLevel: int, label: string, values: list<int>}>, maxSockets: int, harmonyMinimumItemLevel?: int, harmonyLevelFollowsItem?: bool}
     */
    public function optionsFor(int $group, int $index): array
    {
        $this->harmony ??= json_decode(file_get_contents(resource_path('game/mudevs_season_21/harmony-options.json')), true, flags: JSON_THROW_ON_ERROR);
        $harmonyType = $this->harmony['items'][$group.':'.$index]['type'] ?? null;
        $maximumSockets = min(3, $this->catalog->maximumSocketsFor($group, $index));
        $ancientLabels = [];

        if ($group <= 11 && $maximumSockets === 0) {
            foreach ([1, 2, 3] as $variant) {
                $set = $this->catalog->ancientSetFor($group, $index, $variant);

                if ($set !== null) {
                    $ancientLabels[$variant | 4] = $set['name'].' +5';
                    $ancientLabels[$variant | 8] = $set['name'].' +10';
                }
            }
        }

        return [
            'ancientOptions' => array_keys($ancientLabels),
            'ancientOptionLabels' => $ancientLabels,
            'allows380' => false,
            'harmonyMinimumItemLevel' => $this->harmony['items'][$group.':'.$index]['minimumItemLevel'] ?? 16,
            'harmonyLevelFollowsItem' => true,
            'harmonyType' => $maximumSockets === 0 ? $harmonyType : null,
            'harmonyOptions' => $maximumSockets === 0 && $harmonyType !== null
                ? array_map(static fn (array $option): array => [
                    'value' => $option['value'], 'minimumLevel' => $option['minimumLevel'], 'label' => $option['label'], 'values' => $option['values'],
                ], $this->harmony['effects'][$harmonyType] ?? [])
                : [],
            'maxSockets' => $maximumSockets,
        ];
    }
}
