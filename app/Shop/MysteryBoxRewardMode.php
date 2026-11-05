<?php

namespace App\Shop;

/**
 * Defines how many configured items a Mystery Box releases when opened.
 */
enum MysteryBoxRewardMode: string
{
    case OneRandom = 'one_random';
    case AllItems = 'all_items';
}
