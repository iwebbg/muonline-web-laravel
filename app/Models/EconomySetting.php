<?php

namespace App\Models;

use Database\Factories\EconomySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores the active application-owned pricing for paid character services.
 */
#[Fillable([
    'item_generator_markup_percentage',
    'character_rename_enabled',
    'character_rename_price_credits',
    'character_class_change_enabled',
    'character_class_change_price_credits',
    'character_reset_purchase_enabled',
    'character_reset_price_credits',
    'character_level_up_points_purchase_enabled',
    'character_level_up_points_package_size',
    'character_level_up_points_price_credits',
    'revision',
    'updated_by_administrator_id',
])]
final class EconomySetting extends Model
{
    /** @use HasFactory<EconomySettingFactory> */
    use HasFactory;

    /**
     * Casts pricing and feature switches to exact application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_generator_markup_percentage' => 'integer',
            'character_rename_enabled' => 'boolean',
            'character_rename_price_credits' => 'integer',
            'character_class_change_enabled' => 'boolean',
            'character_class_change_price_credits' => 'integer',
            'character_reset_purchase_enabled' => 'boolean',
            'character_reset_price_credits' => 'integer',
            'character_level_up_points_purchase_enabled' => 'boolean',
            'character_level_up_points_package_size' => 'integer',
            'character_level_up_points_price_credits' => 'integer',
            'revision' => 'integer',
            'updated_by_administrator_id' => 'integer',
        ];
    }
}
