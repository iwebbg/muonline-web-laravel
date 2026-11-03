<?php

namespace App\WebStorage;

/**
 * Identifies the exact game storage area used for web-custody transfers.
 */
enum ItemStorageLocation: string
{
    case Warehouse = 'warehouse';
    case Inventory = 'inventory';
}
