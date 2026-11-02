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
        public string $className,
        public int $resets,
        public int $grandResets,
        public bool $online,
    ) {}

    /**
     * Converts the entry to the stable shape consumed by the ranking view.
     *
     * @return array{name: string, class: string, resets: int, grand_resets: int, online: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class' => $this->className,
            'resets' => $this->resets,
            'grand_resets' => $this->grandResets,
            'online' => $this->online,
        ];
    }
}
