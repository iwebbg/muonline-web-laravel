<?php

namespace App\Shop;

use App\Game\Versions\Version97d99i\ItemCatalog97d99i;

/**
 * Normalizes immutable shop snapshots for the shared decoded-item tooltip.
 */
final readonly class ShopItemPresenter
{
    /**
     * Creates the presenter with the trusted version-specific item catalog.
     */
    public function __construct(private ItemCatalog97d99i $itemCatalog) {}

    /**
     * Builds one client-safe tooltip payload from a purchased item snapshot.
     *
     * @param  array<string, mixed>  $item
     * @return array{code: string, name: string, excellentType: int, optionType: int, rarity: string, level: int, durability: int, optionLevel: int, hasSkill: bool, hasLuck: bool, excellentMask: int, ancientOption: int}
     */
    public function present(array $item): array
    {
        $itemCode = (string) ($item['item_code'] ?? '');
        $definition = null;

        if (preg_match('/\A(\d+):(\d+)\z/', $itemCode, $matches) === 1) {
            $definition = $this->itemCatalog->definitionFor(
                (int) $matches[1],
                (int) $matches[2],
            );
        }

        $level = (int) ($item['level'] ?? 0);
        $hasLuck = (bool) ($item['has_luck'] ?? false);
        $excellentMask = (int) ($item['excellent_mask'] ?? 0);
        $ancientOption = (int) ($item['ancient_option'] ?? 0);
        $rarity = 'normal';

        if ($ancientOption > 0) {
            $rarity = 'ancient';
        } elseif ($excellentMask > 0) {
            $rarity = 'excellent';
        } elseif ($level >= 9 || $hasLuck) {
            $rarity = 'rare';
        }

        return [
            'code' => $itemCode,
            'name' => (string) ($item['item_name'] ?? 'Item '.$itemCode),
            'excellentType' => $definition?->excellentType ?? 0,
            'optionType' => $definition?->optionType ?? 0,
            'rarity' => $rarity,
            'level' => $level,
            'durability' => (int) ($item['durability'] ?? 0),
            'optionLevel' => (int) ($item['option_level'] ?? 0),
            'hasSkill' => (bool) ($item['has_skill'] ?? false),
            'hasLuck' => $hasLuck,
            'excellentMask' => $excellentMask,
            'ancientOption' => $ancientOption,
        ];
    }
}
