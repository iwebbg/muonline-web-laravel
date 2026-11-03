<?php

namespace App\Game\Data;

use App\Stats\CharacterStats;

/**
 * Carries one private account character without exposing legacy column names.
 */
final readonly class AccountCharacter
{
    /**
     * Creates an immutable character record for the account dashboard.
     *
     * @param  array<string, int>  $additionalStats
     */
    public function __construct(
        public string $name,
        public string $className,
        public int $classCode,
        public string $image,
        public int $level,
        public int $resets,
        public int $grandResets,
        public int $zen,
        public int $points,
        public int $strength,
        public int $agility,
        public int $vitality,
        public int $energy,
        public string $map,
        public bool $online,
        public array $additionalStats = [],
    ) {}

    /**
     * Returns every base and version-specific attribute as one typed snapshot.
     */
    public function stats(): CharacterStats
    {
        return new CharacterStats(
            strength: $this->strength,
            agility: $this->agility,
            vitality: $this->vitality,
            energy: $this->energy,
            additional: $this->additionalStats,
        );
    }

    /**
     * Converts the character to the stable shape consumed by the Alpine panel.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $stats = $this->stats()->toArray();

        return [
            'name' => $this->name,
            'class' => $this->className,
            'classCode' => $this->classCode,
            'image' => $this->image,
            'level' => $this->level,
            'resets' => $this->resets,
            'grandResets' => $this->grandResets,
            'zen' => $this->zen,
            'points' => $this->points,
            'stats' => $stats,
            ...$stats,
            'map' => $this->map,
            'online' => $this->online,
        ];
    }
}
