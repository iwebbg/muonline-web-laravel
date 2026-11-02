<?php

namespace App\Models;

use App\Content\DownloadCategory;
use App\Content\NewsPostStatus;
use Database\Factories\DownloadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one bilingual application-owned server download link.
 */
#[Fillable([
    'slug',
    'category',
    'title_en',
    'title_bg',
    'description_en',
    'description_bg',
    'version',
    'file_size',
    'download_url',
    'checksum_sha256',
    'sort_order',
    'status',
    'published_at',
    'created_by_administrator_id',
    'updated_by_administrator_id',
])]
final class Download extends Model
{
    /** @use HasFactory<DownloadFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Returns the localized title with required English fallback.
     */
    public function localizedTitle(string $locale): string
    {
        return $this->localizedValue($locale, 'title');
    }

    /**
     * Returns the localized description with required English fallback.
     */
    public function localizedDescription(string $locale): string
    {
        return $this->localizedValue($locale, 'description');
    }

    /**
     * Returns immutable revisions recorded for this download.
     *
     * @return HasMany<DownloadRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DownloadRevision::class);
    }

    /**
     * Returns the complete non-relational state stored in revision history.
     *
     * @return array<string, mixed>
     */
    public function revisionSnapshot(): array
    {
        return [
            'slug' => $this->slug,
            'category' => $this->category->value,
            'title_en' => $this->title_en,
            'title_bg' => $this->title_bg,
            'description_en' => $this->description_en,
            'description_bg' => $this->description_bg,
            'version' => $this->version,
            'file_size' => $this->file_size,
            'download_url' => $this->download_url,
            'checksum_sha256' => $this->checksum_sha256,
            'sort_order' => $this->sort_order,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toAtomString(),
            'deleted_at' => $this->deleted_at?->toAtomString(),
        ];
    }

    /**
     * Casts finite policies and lifecycle timestamps to typed values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DownloadCategory::class,
            'status' => NewsPostStatus::class,
            'published_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Reads one localized database column with the required English fallback.
     */
    private function localizedValue(string $locale, string $field): string
    {
        $localizedAttribute = $field.'_'.$locale;
        $localizedValue = $this->getAttribute($localizedAttribute);

        if (is_string($localizedValue) && $localizedValue !== '') {
            return $localizedValue;
        }

        return (string) $this->getAttribute($field.'_en');
    }
}
