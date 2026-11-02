<?php

namespace App\Models;

use Database\Factories\VipPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one bilingual administrator-managed character VIP offer.
 */
#[Fillable([
    'slug',
    'name_en',
    'name_bg',
    'description_en',
    'description_bg',
    'duration_days',
    'price_credits',
    'enabled',
    'sort_order',
])]
final class VipPlan extends Model
{
    /** @use HasFactory<VipPlanFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Restricts a query to plans currently offered to players.
     *
     * @param  Builder<VipPlan>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * Returns the localized name with required English fallback.
     */
    public function localizedName(string $locale): string
    {
        return $this->localizedValue($locale, 'name');
    }

    /**
     * Returns the localized description with required English fallback.
     */
    public function localizedDescription(string $locale): string
    {
        return $this->localizedValue($locale, 'description');
    }

    /**
     * Casts plan policy values to stable PHP scalar types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_credits' => 'integer',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * Reads one localized database column with the required English fallback.
     */
    private function localizedValue(string $locale, string $field): string
    {
        $localizedValue = $this->getAttribute($field.'_'.$locale);

        if (is_string($localizedValue) && $localizedValue !== '') {
            return $localizedValue;
        }

        return (string) $this->getAttribute($field.'_en');
    }
}
