<?php

namespace App\Game\Data;

/**
 * Carries native account membership evaluated against the game database clock.
 */
final readonly class AccountMembership
{
    /**
     * Retains the stored tier and expiry without inventing names or converting server-local time.
     */
    public function __construct(
        public ?int $storedLevel,
        public ?int $effectiveLevel,
        public ?string $expiresAt,
        public bool $expired,
    ) {}
}
