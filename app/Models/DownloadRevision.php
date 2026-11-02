<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one immutable administrator-authored download revision.
 */
#[Fillable(['download_id', 'administrator_id', 'action', 'snapshot'])]
final class DownloadRevision extends Model
{
    /**
     * Casts the download snapshot to a structured application value.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }
}
