<?php

namespace App\Models;

use App\Game\Operations\CharacterOperationStatus;
use App\Moderation\ModerationAction;
use App\Moderation\ModerationTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records an administrator's idempotent account or character moderation write.
 */
#[Fillable([
    'idempotency_key',
    'administrator_id',
    'target_type',
    'target_identifier',
    'account_id',
    'character_name',
    'action',
    'status',
    'reason',
    'context_snapshot',
    'before_snapshot',
    'after_snapshot',
    'failure_code',
    'completed_at',
])]
final class ModerationOperation extends Model
{
    /**
     * Casts finite moderation values and immutable audit snapshots.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'administrator_id' => 'integer',
            'target_type' => ModerationTargetType::class,
            'action' => ModerationAction::class,
            'status' => CharacterOperationStatus::class,
            'context_snapshot' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
