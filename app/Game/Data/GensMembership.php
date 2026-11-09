<?php

namespace App\Game\Data;

/**
 * Carries one character's standard Gens faction progression.
 */
final readonly class GensMembership
{
    /**
     * Creates an immutable Gens membership snapshot.
     */
    public function __construct(
        public string $characterName,
        public GensFamily $family,
        public int $contribution,
        public int $rank,
    ) {}

    /**
     * Converts the membership to the stable account-workspace shape.
     *
     * @return array{family: string, contribution: int, rank: int}
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family->value,
            'contribution' => $this->contribution,
            'rank' => $this->rank,
        ];
    }
}
