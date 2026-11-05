<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preserves one immutable before-and-after website settings revision.
 */
#[Fillable([
    'website_setting_id',
    'revision',
    'before_snapshot',
    'after_snapshot',
    'changed_by_administrator_id',
])]
final class WebsiteSettingRevision extends Model
{
    /**
     * Returns the administrator identity that published this revision.
     */
    public function changedByAdministrator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'changed_by_administrator_id');
    }

    /**
     * Casts snapshots and revision values for the audit boundary.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'website_setting_id' => 'integer',
            'revision' => 'integer',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'changed_by_administrator_id' => 'integer',
        ];
    }
}
