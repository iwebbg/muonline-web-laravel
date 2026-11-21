<?php

namespace App\Game\Contracts;

/**
 * Restricts generated items to identities and options supported by their native persistence.
 */
interface ItemConstructionPolicy
{
    /**
     * Returns item-specific choices for administrator construction controls.
     *
     * @return array{ancientOptions: list<int>, ancientOptionLabels?: array<int, string>, allows380: bool, harmonyType: ?string, harmonyOptions: list<array{value: int, minimumLevel: int, label?: string, values?: list<int>}>, maxSockets: int, harmonyMinimumItemLevel?: int, harmonyLevelFollowsItem?: bool}
     */
    public function optionsFor(int $group, int $index): array;

    /**
     * Checks construction before allocating a serial or charging for a reward.
     *
     * @param  array<string, int|bool|string|null>  $options  Canonical snake-case construction fields.
     */
    public function allowsConstruction(array $options): bool;
}
