<?php

namespace App\Modules;

use App\Models\ModuleProfile;
use Illuminate\Database\DatabaseManager;

/**
 * Resolves module availability for the active server profile and version.
 */
final class ModuleManager
{
    private ?ModuleSettings $resolvedSettings = null;

    /**
     * Creates the resolver with access to the application schema state.
     */
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Reports whether one module is enabled for the active profile.
     */
    public function enabled(ServerModule $module): bool
    {
        return $this->current()->enabled($module);
    }

    /**
     * Returns the active persisted snapshot or the configured defaults.
     */
    public function current(): ModuleSettings
    {
        if ($this->resolvedSettings instanceof ModuleSettings) {
            return $this->resolvedSettings;
        }

        if (! $this->moduleProfilesTableExists()) {
            $this->resolvedSettings = ModuleSettings::defaults();

            return $this->resolvedSettings;
        }

        $profile = ModuleProfile::query()
            ->where('profile_key', $this->profileKey())
            ->where('game_version', $this->gameVersion())
            ->first();

        $modules = is_array($profile?->modules) ? $profile->modules : [];
        $this->resolvedSettings = ModuleSettings::fromArray($modules);

        return $this->resolvedSettings;
    }

    /**
     * Returns the persisted revision for optimistic administrator updates.
     */
    public function revision(): int
    {
        if (! $this->moduleProfilesTableExists()) {
            return 0;
        }

        return (int) (ModuleProfile::query()
            ->where('profile_key', $this->profileKey())
            ->where('game_version', $this->gameVersion())
            ->value('revision') ?? 0);
    }

    /**
     * Clears the request-local snapshot after an administrator update.
     */
    public function forget(): void
    {
        $this->resolvedSettings = null;
    }

    /**
     * Returns the stable active server profile key.
     */
    public function profileKey(): string
    {
        return (string) config('game.profile.key', 'darksteam_97d99i');
    }

    /**
     * Returns the display version paired with the active profile.
     */
    public function gameVersion(): string
    {
        return (string) config('game.profile.version', '97d + 99i');
    }

    /**
     * Reports whether module persistence is available during staged deployments.
     */
    private function moduleProfilesTableExists(): bool
    {
        return $this->database
            ->connection()
            ->getSchemaBuilder()
            ->hasTable((new ModuleProfile)->getTable());
    }
}
