<?php

namespace App\Shop;

/**
 * Identifies the character-class audience advertised for a shop offer.
 */
enum ShopClassScope: string
{
    case All = 'all';
    case DarkWizard = 'dw_sm';
    case DarkKnight = 'dk_bk';
    case FairyElf = 'elf_me';
    case MagicGladiator = 'mg';
    case DarkLord = 'dl_le';
}
