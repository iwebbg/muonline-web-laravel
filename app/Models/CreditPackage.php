<?php

namespace App\Models;

use App\Database\Casts\LowercaseUuid;
use App\Payments\CreditPackageStatus;
use Database\Factories\CreditPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Defines one administrator-configured Credits purchase option. */
#[Fillable(['public_id', 'title_en', 'title_bg', 'credits', 'price_minor', 'currency', 'status', 'sort_order'])]
final class CreditPackage extends Model
{
    /** @use HasFactory<CreditPackageFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return HasMany<CreditPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    public function localizedTitle(): string
    {
        return app()->getLocale() === 'bg' ? $this->title_bg : $this->title_en;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'public_id' => LowercaseUuid::class,
            'credits' => 'integer',
            'price_minor' => 'integer',
            'status' => CreditPackageStatus::class,
            'sort_order' => 'integer',
        ];
    }
}
