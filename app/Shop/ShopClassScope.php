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
    case Summoner = 'su_bs';
    case RageFighter = 'rf_fm';
    case GrowLancer = 'grow-lancer';
    case RuneWizard = 'rune-wizard';
    case Slayer = 'slayer';
    case GunCrusher = 'gun-crusher';
    case KundunMephis = 'kundun-mephis';
    case Lemuria = 'lemuria';
    case IllusionKnight = 'illusion-knight';
    case Alchemist = 'alchemist';
    case Crusader = 'crusader';
}
