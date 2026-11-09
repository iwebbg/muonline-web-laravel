<?php

namespace App\CharacterServices;

/**
 * Identifies a paid mutation offered for an owned character.
 */
enum CharacterServiceType: string
{
    case Rename = 'rename';
    case ClassChange = 'class_change';
    case ResetPurchase = 'reset_purchase';
    case LevelUpPointsPurchase = 'level_up_points_purchase';
}
