<?php

namespace App\Game\Contracts;

use App\AccountRegistration\AccountRegistrationResult;
use SensitiveParameter;

/**
 * Defines the version adapter that creates one complete game account.
 */
interface AccountRegistrationRepository
{
    /**
     * Atomically inserts the records required by the active game profile.
     */
    public function register(
        string $username,
        #[SensitiveParameter] string $password,
        string $email,
    ): AccountRegistrationResult;
}
