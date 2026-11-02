<?php

namespace App\AccountRegistration;

/**
 * Identifies one safe reason that prevents a legacy account registration.
 */
enum AccountRegistrationBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case PasswordSchemeUnsupported = 'password_scheme_unsupported';
    case AccountAlreadyExists = 'account_already_exists';
}
