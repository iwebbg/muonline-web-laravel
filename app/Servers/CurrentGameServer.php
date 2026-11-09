<?php

namespace App\Servers;

use App\Models\GameServer;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves administrator-managed metadata for the server powered by this site.
 */
final class CurrentGameServer
{
    /** @var array<string, string> */
    private array $resolvedValues = [];

    /**
     * Returns the current server name with a profile fallback before setup.
     */
    public function name(): string
    {
        return $this->value('name', (string) config('game.profile.name'));
    }

    /**
     * Returns the current server version with a profile fallback before setup.
     */
    public function version(): string
    {
        return $this->value('version', (string) config('game.profile.version'));
    }

    /**
     * Resolves one editable current-server field without assuming migrations ran.
     */
    private function value(string $column, string $fallback): string
    {
        if (array_key_exists($column, $this->resolvedValues)) {
            return $this->resolvedValues[$column];
        }

        if (! Schema::hasTable('game_servers')) {
            return $this->resolvedValues[$column] = $fallback;
        }

        $value = GameServer::query()
            ->where('is_current', true)
            ->value($column);

        return $this->resolvedValues[$column] = is_string($value) && trim($value) !== ''
            ? $value
            : $fallback;
    }
}
