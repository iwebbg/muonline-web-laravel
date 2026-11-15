<?php

namespace App\Game\Contracts;

/**
 * Restricts generated items to identities and options supported by their native persistence.
 */
interface ItemConstructionPolicy
{
    /**
     * Checks construction before allocating a serial or charging for a reward.
     *
     * @param  array<string, int|bool|string|null>  $options  Canonical snake-case construction fields.
     */
    public function allowsConstruction(array $options): bool;
}
