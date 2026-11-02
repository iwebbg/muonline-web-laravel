<?php

namespace App\Modules\Actions;

use App\Exceptions\Modules\ModuleProfileRevisionConflict;
use App\Models\Administrator;
use App\Models\ModuleProfile;
use App\Models\ModuleProfileRevision;
use App\Modules\ModuleManager;
use App\Modules\ModuleSettings;
use Illuminate\Database\DatabaseManager;

/**
 * Persists one complete module snapshot and immutable audit revision atomically.
 */
final readonly class UpdateModuleProfile
{
    /**
     * Creates the action with its transactional and profile dependencies.
     */
    public function __construct(
        private DatabaseManager $database,
        private ModuleManager $moduleManager,
    ) {}

    /**
     * Saves a module profile only when the submitted revision is current.
     *
     * @throws ModuleProfileRevisionConflict When another administrator saved first.
     */
    public function execute(
        ModuleSettings $settings,
        int $expectedRevision,
        Administrator $administrator,
    ): ModuleProfile {
        $profile = $this->database->transaction(function () use (
            $settings,
            $expectedRevision,
            $administrator,
        ): ModuleProfile {
            $profile = ModuleProfile::query()
                ->where('profile_key', $this->moduleManager->profileKey())
                ->where('game_version', $this->moduleManager->gameVersion())
                ->lockForUpdate()
                ->first();
            $currentRevision = $profile?->revision ?? 0;

            if ($currentRevision !== $expectedRevision) {
                throw new ModuleProfileRevisionConflict;
            }

            $beforeSnapshot = $profile === null
                ? ModuleSettings::defaults()->toArray()
                : ModuleSettings::fromArray($profile->modules)->toArray();
            $nextRevision = $currentRevision + 1;
            $attributes = [
                'profile_key' => $this->moduleManager->profileKey(),
                'game_version' => $this->moduleManager->gameVersion(),
                'modules' => $settings->toArray(),
                'revision' => $nextRevision,
                'updated_by_administrator_id' => $administrator->getKey(),
            ];

            if ($profile === null) {
                $profile = ModuleProfile::query()->create($attributes);
            } else {
                $profile->update($attributes);
            }

            ModuleProfileRevision::query()->create([
                'module_profile_id' => $profile->getKey(),
                'revision' => $nextRevision,
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $settings->toArray(),
                'changed_by_administrator_id' => $administrator->getKey(),
            ]);

            return $profile->refresh();
        });

        $this->moduleManager->forget();

        return $profile;
    }
}
