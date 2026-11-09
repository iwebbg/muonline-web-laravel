<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records immutable before and after snapshots of paid-service settings.
 */
#[Fillable(['economy_setting_id', 'revision', 'before_snapshot', 'after_snapshot', 'changed_by_administrator_id'])]
final class EconomySettingRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * Casts revision snapshots to structures safe for audit rendering.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'economy_setting_id' => 'integer',
            'revision' => 'integer',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'changed_by_administrator_id' => 'integer',
        ];
    }
}
