<?php

namespace App\Shop;

/**
 * Identifies whether an administrator-curated offer is publicly purchasable.
 */
enum ShopOfferStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
