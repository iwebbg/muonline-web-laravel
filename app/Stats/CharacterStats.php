<?php

namespace App\Stats;

/**
 * Carries the four mutable MuOnline character attributes as one typed value.
 */
final readonly class CharacterStats
{
    /**
     * Creates an immutable character attribute snapshot.
     */
    public function __construct(
        public int $strength,
        public int $agility,
        public int $vitality,
        public int $energy,
    ) {}

    /**
     * Calculates the resulting attributes after one allocation.
     */
    public function add(StatAllocation $allocation): self
    {
        return new self(
            strength: $this->strength + $allocation->strength,
            agility: $this->agility + $allocation->agility,
            vitality: $this->vitality + $allocation->vitality,
            energy: $this->energy + $allocation->energy,
        );
    }

    /**
     * Converts the attributes to the stable client and audit shape.
     *
     * @return array{strength: int, agility: int, vitality: int, energy: int}
     */
    public function toArray(): array
    {
        return [
            'strength' => $this->strength,
            'agility' => $this->agility,
            'vitality' => $this->vitality,
            'energy' => $this->energy,
        ];
    }
}
