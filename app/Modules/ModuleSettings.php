<?php

namespace App\Modules;

/**
 * Carries one complete, typed module availability snapshot.
 */
final readonly class ModuleSettings
{
    /**
     * Creates a snapshot from an allow-listed module map.
     *
     * @param  array<string, bool>  $states
     */
    public function __construct(
        private array $states,
    ) {}

    /**
     * Builds settings from persisted values with safe configured defaults.
     *
     * @param  array<string, mixed>  $persistedStates
     */
    public static function fromArray(array $persistedStates): self
    {
        $states = [];

        foreach (ServerModule::cases() as $module) {
            $defaultEnabled = (bool) config('modules.defaults.'.$module->value, false);
            $states[$module->value] = array_key_exists($module->value, $persistedStates)
                ? (bool) $persistedStates[$module->value]
                : $defaultEnabled;
        }

        return new self($states);
    }

    /**
     * Returns the configured defaults when no profile has been persisted.
     */
    public static function defaults(): self
    {
        return self::fromArray([]);
    }

    /**
     * Reports whether one player-facing module is currently available.
     */
    public function enabled(ServerModule $module): bool
    {
        return $this->states[$module->value] ?? false;
    }

    /**
     * Returns the complete allow-listed map for persistence and rendering.
     *
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return $this->states;
    }
}
