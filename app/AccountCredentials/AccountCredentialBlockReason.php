<?php

namespace App\AccountCredentials;

/**
 * Identifies one safe reason that prevents an account credential update.
 */
enum AccountCredentialBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case PasswordSchemeUnsupported = 'password_scheme_unsupported';
    case AccountNotFound = 'account_not_found';
    case CurrentPasswordInvalid = 'current_password_invalid';
    case NoChanges = 'no_changes';
    case OperationInProgress = 'operation_in_progress';
}
