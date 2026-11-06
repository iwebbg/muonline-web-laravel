<?php

namespace App\Game;

/**
 * Interprets the legacy account connection value for fail-closed game writes.
 */
final class GameAccountWriteStatus
{
    /**
     * Reports whether a locked status value proves that the account is offline.
     */
    public static function allowsWrites(mixed $connectStatus): bool
    {
        return $connectStatus === 0 || $connectStatus === '0';
    }

    /**
     * Blocks writes for connected, missing, null or otherwise unknown statuses.
     */
    public static function blocksWrites(mixed $connectStatus): bool
    {
        return ! self::allowsWrites($connectStatus);
    }
}
