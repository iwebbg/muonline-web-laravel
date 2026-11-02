<?php

namespace App\Game\Contracts;

use App\AccountRecovery\PasswordResetRecipient;
use SensitiveParameter;

/**
 * Defines version-specific account lookup and password replacement for recovery.
 */
interface AccountPasswordRecoveryRepository
{
    /**
     * Returns a recipient only when the account and normalized email match.
     */
    public function findRecipient(string $accountId, string $email): ?PasswordResetRecipient;

    /**
     * Atomically replaces the legacy game password after a token is claimed.
     */
    public function replacePassword(
        string $accountId,
        #[SensitiveParameter] string $newPassword,
    ): void;
}
