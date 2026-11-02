<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records password-free before and after snapshots for GM identity changes.
 */
#[Fillable([
    'target_administrator_id',
    'changed_by_administrator_id',
    'action',
    'before_snapshot',
    'after_snapshot',
])]
final class AdministratorIdentityRevision extends Model
{
    /**
     * Casts immutable identity snapshots to structured application values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
        ];
    }
}
