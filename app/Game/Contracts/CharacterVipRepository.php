<?php

namespace App\Game\Contracts;

use DateTimeInterface;

/**
 * Defines ownership-scoped character VIP synchronization writes.
 */
interface CharacterVipRepository
{
    /**
     * Sets one offline owned character's VIP expiry in the game database.
     */
    public function synchronize(
        string $accountId,
        string $characterName,
        DateTimeInterface $expiresAt,
    ): void;
}
