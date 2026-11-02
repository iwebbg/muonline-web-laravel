<?php

namespace App\AccountRecovery;

/**
 * Describes stable failures from the game account password-recovery boundary.
 */
enum PasswordRecoveryBlockReason: string
{
    case InvalidToken = 'invalid_token';
    case WritesDisabled = 'writes_disabled';
    case PasswordSchemeUnsupported = 'password_scheme_unsupported';
    case AccountNotFound = 'account_not_found';
    case OperationFailed = 'operation_failed';
}
