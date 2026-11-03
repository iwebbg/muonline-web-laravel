<?php

namespace App\WebStorage;

/**
 * Identifies which player module owns one exact-item custody record.
 */
enum WebStorageContext: string
{
    case WebVault = 'web_vault';
    case JewelBank = 'jewel_bank';
}
