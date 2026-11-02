<?php

namespace App\Game\Operations;

/**
 * Identifies the durable application-side state of a character mutation.
 */
enum CharacterOperationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
