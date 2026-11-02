<?php

namespace App\Moderation;

/**
 * Identifies whether a moderation operation blocks or restores a game target.
 */
enum ModerationAction: string
{
    case Block = 'block';
    case Unblock = 'unblock';

    /**
     * Returns the legacy blocked state produced by this action.
     */
    public function blockedState(): bool
    {
        return $this === self::Block;
    }
}
