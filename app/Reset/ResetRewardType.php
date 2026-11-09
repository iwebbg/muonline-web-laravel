<?php

namespace App\Reset;

/**
 * Identifies the optional account reward granted after a completed reset.
 */
enum ResetRewardType: string
{
    case None = 'none';
    case Credits = 'credits';
    case MysteryBox = 'mystery_box';
}
