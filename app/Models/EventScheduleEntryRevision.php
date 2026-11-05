<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one immutable administrator change to an event schedule override.
 */
#[Fillable([
    'event_schedule_entry_id',
    'revision',
    'action',
    'before_snapshot',
    'after_snapshot',
    'changed_by_administrator_id',
])]
final class EventScheduleEntryRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * Casts revision snapshots to structured arrays.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_schedule_entry_id' => 'integer',
            'revision' => 'integer',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'changed_by_administrator_id' => 'integer',
        ];
    }
}
