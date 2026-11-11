<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores the single website-wide branding and search metadata snapshot.
 */
#[Fillable([
    'brand_title',
    'brand_subtitle',
    'logo_path',
    'home_title_en',
    'home_title_bg',
    'page_title_suffix',
    'meta_description_en',
    'meta_description_bg',
    'meta_keywords_en',
    'meta_keywords_bg',
    'og_image_path',
    'copyright_holder',
    'theme_slug',
    'revision',
    'updated_by_administrator_id',
])]
final class WebsiteSetting extends Model
{
    /**
     * Casts the optimistic revision to a stable integer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['revision' => 'integer'];
    }
}
