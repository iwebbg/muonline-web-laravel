<?php

namespace App\CharacterServices;

/**
 * Carries exact before and after snapshots from one game database mutation.
 */
final readonly class CharacterServiceResult
{
    /**
     * Creates one auditable character mutation result.
     *
     * @param  array<string, int|string>  $before
     * @param  array<string, int|string>  $after
     */
    public function __construct(
        public array $before,
        public array $after,
    ) {}
}
