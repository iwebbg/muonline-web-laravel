<?php

namespace App\AccountCredentials;

/**
 * Identifies the durable audit state of one account credential update.
 */
enum AccountCredentialStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
