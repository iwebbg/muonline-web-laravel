<?php

namespace App\Models;

use Database\Factories\GameServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents one public server destination in an owner's server network.
 */
#[Fillable([
    'slug',
    'name',
    'version',
    'experience',
    'drop',
    'capacity',
    'online_count',
    'is_online',
    'is_current',
    'is_visible',
    'website_url',
    'sort_order',
])]
final class GameServer extends Model
{
    /** @use HasFactory<GameServerFactory> */
    use HasFactory;

    /**
     * Casts public counters and server flags to stable scalar types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'online_count' => 'integer',
            'is_online' => 'boolean',
            'is_current' => 'boolean',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
