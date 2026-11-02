<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one administrator override for a profile-aware realm event schedule.
 */
#[Fillable([
    'profile_key',
    'game_version',
    'event_key',
    'name_en',
    'name_bg',
    'location_en',
    'location_bg',
    'accent',
    'enabled',
    'duration_minutes',
    'schedule_mode',
    'times',
    'days_of_week',
    'interval_minutes',
    'interval_anchor',
    'source',
    'sort_order',
    'revision',
    'updated_by_administrator_id',
])]
final class EventScheduleEntry extends Model
{
    /**
     * Returns the requested translation with required English fallback.
     */
    public function localizedName(string $locale): string
    {
        return $this->localizedValue('name', $locale);
    }

    /**
     * Returns the requested location with required English fallback.
     */
    public function localizedLocation(string $locale): string
    {
        return $this->localizedValue('location', $locale);
    }

    /**
     * Casts schedule fields to stable domain-facing types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'duration_minutes' => 'integer',
            'times' => 'array',
            'days_of_week' => 'array',
            'interval_minutes' => 'integer',
            'sort_order' => 'integer',
            'revision' => 'integer',
        ];
    }

    /**
     * Resolves one bilingual field without allowing an empty fallback.
     */
    private function localizedValue(string $field, string $locale): string
    {
        $localizedValue = $this->getAttribute($field.'_'.$locale);

        if (is_string($localizedValue) && $localizedValue !== '') {
            return $localizedValue;
        }

        return (string) $this->getAttribute($field.'_en');
    }
}
