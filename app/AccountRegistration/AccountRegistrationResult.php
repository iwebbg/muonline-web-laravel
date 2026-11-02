<?php

namespace App\AccountRegistration;

/**
 * Carries the non-sensitive identifiers produced by a completed registration.
 */
final readonly class AccountRegistrationResult
{
    /**
     * Creates an immutable result without retaining the submitted password.
     */
    public function __construct(
        public int $accountGuid,
        public string $accountId,
        public bool $billingRecordCreated,
    ) {}

    /**
     * Returns the safe audit snapshot for the completed game mutation.
     *
     * @return array{account_guid: int, account_id: string, memb_info_created: true, vi_curr_info_created: bool}
     */
    public function toAuditSnapshot(): array
    {
        return [
            'account_guid' => $this->accountGuid,
            'account_id' => $this->accountId,
            'memb_info_created' => true,
            'vi_curr_info_created' => $this->billingRecordCreated,
        ];
    }
}
