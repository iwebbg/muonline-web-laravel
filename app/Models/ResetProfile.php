<?php

namespace App\Models;

use App\GrandReset\GrandResetPointsMode;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use Database\Factories\ResetProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores the currently active application-owned reset policy draft.
 */
#[Fillable(['enabled', 'required_level', 'maximum_resets', 'maximum_stat', 'grand_reset_required_resets', 'base_zen_cost', 'multiply_cost_by_reset', 'clear_stats', 'points_mode', 'points_per_reset', 'inventory_policy', 'grand_reset_enabled', 'grand_reset_required_level', 'maximum_grand_resets', 'grand_reset_base_zen_cost', 'grand_reset_multiply_cost', 'grand_reset_clear_stats', 'grand_reset_points_mode', 'grand_reset_points', 'grand_reset_inventory_policy', 'grand_reset_consume_required_resets', 'revision', 'updated_by_administrator_id'])]
final class ResetProfile extends Model
{
    /** @use HasFactory<ResetProfileFactory> */
    use HasFactory;

    /**
     * Defines typed reset policy columns.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'required_level' => 'integer',
            'maximum_resets' => 'integer',
            'maximum_stat' => 'integer',
            'grand_reset_required_resets' => 'integer',
            'grand_reset_enabled' => 'boolean',
            'grand_reset_required_level' => 'integer',
            'maximum_grand_resets' => 'integer',
            'grand_reset_base_zen_cost' => 'integer',
            'grand_reset_multiply_cost' => 'boolean',
            'grand_reset_clear_stats' => 'boolean',
            'grand_reset_points_mode' => GrandResetPointsMode::class,
            'grand_reset_points' => 'integer',
            'grand_reset_inventory_policy' => InventoryPolicy::class,
            'grand_reset_consume_required_resets' => 'boolean',
            'base_zen_cost' => 'integer',
            'multiply_cost_by_reset' => 'boolean',
            'clear_stats' => 'boolean',
            'points_mode' => PointsMode::class,
            'points_per_reset' => 'integer',
            'inventory_policy' => InventoryPolicy::class,
            'revision' => 'integer',
        ];
    }
}
