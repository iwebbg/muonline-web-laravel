<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one immutable administrator-authored news article revision.
 */
#[Fillable(['news_post_id', 'administrator_id', 'action', 'snapshot'])]
final class NewsPostRevision extends Model
{
    /**
     * Casts the article snapshot to a structured application value.
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
