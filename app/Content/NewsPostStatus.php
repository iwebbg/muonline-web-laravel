<?php

namespace App\Content;

/**
 * Identifies whether an application-owned news article is publicly visible.
 */
enum NewsPostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
