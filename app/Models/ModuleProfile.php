<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one module availability snapshot for a server profile and version.
 */
#[Fillable([
    'profile_key',
    'game_version',
    'modules',
    'revision',
    'updated_by_administrator_id',
])]
final class ModuleProfile extends Model
{
    /**
     * Casts the snapshot and revision to stable application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'revision' => 'integer',
        ];
    }
}
