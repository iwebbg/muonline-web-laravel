<?php

namespace App\Reset;

enum InventoryPolicy: string
{
    case KeepAll = 'keep_all';
    case ClearBag = 'clear_bag';
    case ClearAll = 'clear_all';
}
