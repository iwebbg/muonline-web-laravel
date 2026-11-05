<?php

namespace App\Models;

use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use Database\Factories\NewsPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents one bilingual application-owned content entry with English fallback.
 */
#[Fillable([
    'slug',
    'type',
    'title_en',
    'title_bg',
    'excerpt_en',
    'excerpt_bg',
    'body_en',
    'body_bg',
    'status',
    'published_at',
    'created_by_administrator_id',
    'updated_by_administrator_id',
])]
final class NewsPost extends Model
{
    /** @use HasFactory<NewsPostFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Returns the localized title and falls back to English when untranslated.
     */
    public function localizedTitle(string $locale): string
    {
        return $this->localizedValue($locale, 'title');
    }

    /**
     * Returns the localized summary and falls back to English when untranslated.
     */
    public function localizedExcerpt(string $locale): string
    {
        return $this->localizedValue($locale, 'excerpt');
    }

    /**
     * Returns the localized article body and falls back to English when untranslated.
     */
    public function localizedBody(string $locale): string
    {
        return $this->localizedValue($locale, 'body');
    }

    /**
     * Returns immutable content revisions recorded for this article.
     *
     * @return HasMany<NewsPostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(NewsPostRevision::class);
    }

    /**
     * Returns the complete non-relational article state stored in revision history.
     *
     * @return array<string, mixed>
     */
    public function revisionSnapshot(): array
    {
        return [
            'slug' => $this->slug,
            'type' => $this->type->value,
            'title_en' => $this->title_en,
            'title_bg' => $this->title_bg,
            'excerpt_en' => $this->excerpt_en,
            'excerpt_bg' => $this->excerpt_bg,
            'body_en' => $this->body_en,
            'body_bg' => $this->body_bg,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toAtomString(),
            'deleted_at' => $this->deleted_at?->toAtomString(),
        ];
    }

    /**
     * Casts article publication and lifecycle state to typed values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NewsPostStatus::class,
            'type' => NewsPostType::class,
            'created_by_administrator_id' => 'integer',
            'updated_by_administrator_id' => 'integer',
            'published_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
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
