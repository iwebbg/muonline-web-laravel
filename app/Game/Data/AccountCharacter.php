<?php

namespace App\Game\Data;

/**
 * Carries one private account character without exposing legacy column names.
 */
final readonly class AccountCharacter
{
    /**
     * Creates an immutable character record for the account dashboard.
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
    ) {}

    /**
     * Converts the character to the stable shape consumed by the Alpine panel.
     *
     * @return array{name: string, class: string, image: string, level: int, resets: int, grandResets: int, zen: int, points: int, strength: int, agility: int, vitality: int, energy: int, map: string, online: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class' => $this->className,
            'image' => $this->image,
            'level' => $this->level,
            'resets' => $this->resets,
            'grandResets' => $this->grandResets,
            'zen' => $this->zen,
            'points' => $this->points,
            'strength' => $this->strength,
            'agility' => $this->agility,
            'vitality' => $this->vitality,
            'energy' => $this->energy,
            'map' => $this->map,
            'online' => $this->online,
        ];
    }
}
