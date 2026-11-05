<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one immutable before-and-after module configuration revision.
 */
#[Fillable([
    'module_profile_id',
    'revision',
    'before_snapshot',
    'after_snapshot',
    'changed_by_administrator_id',
])]
final class ModuleProfileRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * Casts immutable audit snapshots to structured arrays.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_profile_id' => 'integer',
            'revision' => 'integer',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'changed_by_administrator_id' => 'integer',
        ];
    }
}
