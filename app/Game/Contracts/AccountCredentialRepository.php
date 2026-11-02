<?php

namespace App\Game\Contracts;

use App\AccountCredentials\AccountCredentialResult;
use SensitiveParameter;

/**
 * Defines the version adapter for authenticated account credential changes.
 */
interface AccountCredentialRepository
{
    /**
     * Revalidates the current password and atomically updates the owned account.
     */
    public function update(
        string $accountId,
        #[SensitiveParameter] string $currentPassword,
        string $email,
        #[SensitiveParameter] ?string $newPassword,
    ): AccountCredentialResult;
}
