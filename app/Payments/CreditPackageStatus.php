<?php

namespace App\Payments;

/** Controls whether a Credits package is visible for purchase. */
enum CreditPackageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
