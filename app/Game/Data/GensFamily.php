<?php

namespace App\Game\Data;

/**
 * Identifies the two standard Gens factions independently of database codes.
 */
enum GensFamily: string
{
    case Duprian = 'duprian';
    case Vanert = 'vanert';

    /**
     * Resolves the legacy Season 6 family code or rejects unknown extensions.
     */
    public static function fromDatabaseValue(int $family): ?self
    {
        return match ($family) {
            1 => self::Duprian,
            2 => self::Vanert,
            default => null,
        };
    }
}
