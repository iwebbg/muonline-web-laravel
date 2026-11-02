<?php

namespace App\AccountCredentials;

/**
 * Carries the non-sensitive outcome of one account credential update.
 */
final readonly class AccountCredentialResult
{
    /**
     * Creates an immutable result without retaining credentials or email values.
     */
    public function __construct(
        public string $accountId,
        public bool $emailChanged,
        public bool $passwordChanged,
        public bool $emailPreviouslyPresent,
        public bool $emailCurrentlyPresent,
    ) {}

    /**
     * Returns the safe state observed before the credential mutation.
     *
     * @return array{email_present: bool, password_scheme: string}
     */
    public function beforeAuditSnapshot(): array
    {
        return [
            'email_present' => $this->emailPreviouslyPresent,
            'password_scheme' => 'plaintext',
        ];
    }

    /**
     * Returns the safe state after the mutation without exposing credential values.
     *
     * @return array{email_present: bool, email_changed: bool, password_changed: bool, password_scheme: string}
     */
    public function afterAuditSnapshot(): array
    {
        return [
            'email_present' => $this->emailCurrentlyPresent,
            'email_changed' => $this->emailChanged,
            'password_changed' => $this->passwordChanged,
            'password_scheme' => 'plaintext',
        ];
    }
}
