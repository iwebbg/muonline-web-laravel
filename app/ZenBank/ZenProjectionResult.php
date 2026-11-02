<?php

namespace App\ZenBank;

/**
 * Describes one compare-and-set projection into the game warehouse balance.
 */
enum ZenProjectionResult: string
{
    case Applied = 'applied';
    case AlreadyApplied = 'already_applied';
    case Conflict = 'conflict';
}
