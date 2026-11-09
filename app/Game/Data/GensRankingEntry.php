<?php

namespace App\Game\Data;

/**
 * Carries one normalized public Gens ranking row across game versions.
 */
final readonly class GensRankingEntry
{
    /**
     * Creates an immutable public Gens ranking entry.
     */
    public function __construct(
        public string $name,
        public int $classCode,
        public string $className,
        public string $classAbbreviation,
        public string $image,
        public GensFamily $family,
        public int $contribution,
        public int $familyRank,
        public bool $online,
    ) {}

    /**
     * Converts the entry to the stable shape consumed by the ranking view.
     *
     * @return array{name: string, class_code: int, class: string, class_abbreviation: string, image: string, family: string, contribution: int, family_rank: int, online: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class_code' => $this->classCode,
            'class' => $this->className,
            'class_abbreviation' => $this->classAbbreviation,
            'image' => $this->image,
            'family' => $this->family->value,
            'contribution' => $this->contribution,
            'family_rank' => $this->familyRank,
            'online' => $this->online,
        ];
    }
}
