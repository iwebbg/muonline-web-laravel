<?php

namespace App\Shop;

use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\Contracts\GameVersionProfile;

/**
 * Labels shop audiences with the class evolutions supported by the active season.
 */
final class ShopClassScopePresenter
{
    /** @var array<string, string> Resolved labels shared by filters and offer cards. */
    private array $labels = [];

    /**
     * Uses the active adapter as the authority for available class evolutions.
     */
    public function __construct(private readonly GameVersionProfile $profile) {}

    /**
     * Returns every supported evolution in progression order without changing filter keys.
     */
    public function label(string $scope): string
    {
        if ($scope === 'all') {
            return __('ui.shop.all_classes');
        }

        if (isset($this->labels[$scope])) {
            return $this->labels[$scope];
        }

        $family = match ($scope) {
            'dw_sm' => 'wizard',
            'dk_bk' => 'knight',
            'elf_me' => 'elf',
            'mg' => 'magic-gladiator',
            'dl_le' => 'dark-lord',
            'su_bs' => 'summoner',
            'rf_fm' => 'rage-fighter',
            default => $scope,
        };
        $classes = array_filter(
            $this->profile->classes(),
            static fn (CharacterClassDefinition $class): bool => $class->family === $family,
        );
        usort(
            $classes,
            static fn (CharacterClassDefinition $first, CharacterClassDefinition $second): int => $first->evolutionTier <=> $second->evolutionTier,
        );
        $names = array_column($classes, 'name');

        return $this->labels[$scope] = $names === []
            ? __('ui.shop.class_scopes.'.$scope)
            : implode(' / ', $names);
    }
}
