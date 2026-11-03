<?php

namespace App\Game\Data;

/**
 * Carries one normalized character ranking row across game versions.
 */
final readonly class CharacterRankingEntry
{
    /**
     * Creates an immutable public character ranking entry.
     */
    public function __construct(
        public string $name,
        public int $classCode,
        public string $className,
        public string $classAbbreviation,
        public string $image,
        public int $level,
        public int $resets,
        public int $grandResets,
        public int $pkCount,
        public int $pkLevel,
        public bool $online,
    ) {}

    /**
     * Converts the entry to the stable shape consumed by the ranking view.
     *
     * @return array{name: string, class_code: int, class: string, class_abbreviation: string, image: string, level: int, resets: int, grand_resets: int, pk_count: int, pk_level: int, online: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class_code' => $this->classCode,
            'class' => $this->className,
            'class_abbreviation' => $this->classAbbreviation,
            'image' => $this->image,
            'level' => $this->level,
            'resets' => $this->resets,
            'grand_resets' => $this->grandResets,
            'pk_count' => $this->pkCount,
            'pk_level' => $this->pkLevel,
            'online' => $this->online,
        ];
    }
}
