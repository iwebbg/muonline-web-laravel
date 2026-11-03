<?php

namespace App\Content;

/**
 * Identifies the public content collection an article belongs to.
 */
enum NewsPostType: string
{
    case News = 'news';
    case Guide = 'guide';
    case Page = 'page';
}
