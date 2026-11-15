<?php

namespace App\Modules;

use App\Game\Versions\Contracts\GameVersionProfile;
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
        private readonly GameVersionProfile $versionProfile,
    ) {}

    /**
     * Reports whether one module is enabled for the active profile.
     */
    public function enabled(ServerModule $module): bool
    {
        return $this->current()->enabled($module);
    }

    /**
     * Reports whether the active adapter implements a module before operator settings apply.
     */
    public function supports(ServerModule $module): bool
    {
        $capabilities = $this->versionProfile->capabilities();
        if ($module === ServerModule::GameCurrencyExchange && ! $capabilities->gameCurrencyExchange) {
            return false;
        }

        if ($capabilities->availableModules === null) {
            return ! $capabilities->readOnly;
        }

        return in_array($module, $capabilities->availableModules, true);
    }

    /**
     * Returns the active persisted snapshot or the configured defaults.
     */
    public function current(): ModuleSettings
    {
        if ($this->resolvedSettings instanceof ModuleSettings) {
            return $this->resolvedSettings;
        }

        if ($this->versionProfile->capabilities()->readOnly
            && $this->versionProfile->capabilities()->availableModules === null) {
            $states = [];

            foreach (ServerModule::cases() as $module) {
                $states[$module->value] = false;
            }

            return $this->resolvedSettings = new ModuleSettings($states);
        }

        if (! $this->moduleProfilesTableExists()) {
            $this->resolvedSettings = $this->supportedSettings(ModuleSettings::defaults());

            return $this->resolvedSettings;
        }

        $profile = ModuleProfile::query()
            ->where('profile_key', $this->profileKey())
            ->where('game_version', $this->gameVersion())
            ->first();

        $modules = is_array($profile?->modules) ? $profile->modules : [];
        $this->resolvedSettings = $this->supportedSettings(ModuleSettings::fromArray($modules));

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
     * Keeps unsupported adapter modules disabled even if persisted settings enable them.
     */
    private function supportedSettings(ModuleSettings $settings): ModuleSettings
    {
        $availableModules = $this->versionProfile->capabilities()->availableModules;

        $states = $settings->toArray();
        if (! $this->versionProfile->capabilities()->gameCurrencyExchange) {
            $states[ServerModule::GameCurrencyExchange->value] = false;
        }
        if ($availableModules === null) {
            return new ModuleSettings($states);
        }

        foreach (ServerModule::cases() as $module) {
            if (! in_array($module, $availableModules, true)) {
                $states[$module->value] = false;
            }
        }

        return new ModuleSettings($states);
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
