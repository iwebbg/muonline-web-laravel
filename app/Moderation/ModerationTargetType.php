<?php

namespace App\Moderation;

/**
 * Identifies the verified 97d+99i record controlled by a moderation operation.
 */
enum ModerationTargetType: string
{
    case Account = 'account';
    case Character = 'character';
}
