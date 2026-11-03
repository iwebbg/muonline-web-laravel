<?php

namespace App\Game\Contracts;

/**
 * Defines the version adapter that activates one email-verified game account.
 */
interface AccountActivationRepository
{
    /**
     * Unblocks the exact legacy account after its email token is verified.
     */
    public function activate(string $accountId): bool;
}
