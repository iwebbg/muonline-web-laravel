<?php

namespace App\AccountRecovery;

/**
 * Carries the verified account and email destination for a reset message.
 */
final readonly class PasswordResetRecipient
{
    /**
     * Creates one non-secret password-reset recipient.
     */
    public function __construct(
        public string $accountId,
        public string $email,
    ) {}
}
