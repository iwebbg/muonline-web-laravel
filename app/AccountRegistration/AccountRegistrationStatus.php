<?php

namespace App\AccountRegistration;

/**
 * Identifies the durable audit state of one game-account registration attempt.
 */
enum AccountRegistrationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
