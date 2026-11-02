<?php

namespace App\Models;

use Database\Factories\ResetProfileRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records immutable before and after snapshots for each reset policy change.
 */
#[Fillable(['reset_profile_id', 'revision', 'before_snapshot', 'after_snapshot', 'changed_by_administrator_id'])]
final class ResetProfileRevision extends Model
{
    /** @use HasFactory<ResetProfileRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Casts audit snapshots to structured arrays for safe rendering.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
        ];
    }
}
