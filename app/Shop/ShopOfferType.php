<?php

namespace App\Shop;

/**
 * Identifies whether an offer reveals an item or grants a sealed reward pool.
 */
enum ShopOfferType: string
{
    case Item = 'item';
    case MysteryBox = 'mystery_box';
}
