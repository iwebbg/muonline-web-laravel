<?php

namespace App\Models;

use Database\Factories\VoteSiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stores one administrator-managed vote destination and reward policy source.
 */
#[Fillable([
    'slug',
    'name_en',
    'name_bg',
    'url',
    'image_url',
    'cooldown_hours',
    'reward_credits',
    'reward_zen',
    'auto_reward_on_click',
    'enabled',
    'sort_order',
])]
final class VoteSite extends Model
{
    /** @use HasFactory<VoteSiteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Returns every claim initiated for this vote destination.
     *
     * @return HasMany<VoteClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(VoteClaim::class);
    }

    /**
     * Returns the localized name with English as the required fallback.
     */
    public function localizedName(?string $locale = null): string
    {
        $activeLocale = $locale ?? app()->getLocale();

        if ($activeLocale === 'bg' && filled($this->name_bg)) {
            return (string) $this->name_bg;
        }

        return $this->name_en;
    }

    /**
     * Casts vote policy values to stable PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cooldown_hours' => 'integer',
            'reward_credits' => 'integer',
            'reward_zen' => 'integer',
            'auto_reward_on_click' => 'boolean',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
