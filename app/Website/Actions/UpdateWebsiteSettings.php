<?php

namespace App\Website\Actions;

use App\Exceptions\WebsiteSettingRevisionConflict;
use App\Models\Administrator;
use App\Models\WebsiteSetting;
use App\Models\WebsiteSettingRevision;
use App\Website\WebsiteSettings;
use App\Website\WebsiteSettingsManager;
use Illuminate\Database\DatabaseManager;

/**
 * Publishes one atomic website settings snapshot with immutable revision history.
 */
final readonly class UpdateWebsiteSettings
{
    /**
     * Creates the action with its transaction and lifecycle settings dependencies.
     */
    public function __construct(
        private DatabaseManager $database,
        private WebsiteSettingsManager $settingsManager,
    ) {}

    /**
     * Saves the snapshot when the administrator submitted the current revision.
     *
     * @throws WebsiteSettingRevisionConflict When another administrator saved first.
     */
    public function execute(
        WebsiteSettings $settings,
        int $expectedRevision,
        Administrator $administrator,
    ): WebsiteSetting {
        $setting = $this->database->transaction(function () use (
            $settings,
            $expectedRevision,
            $administrator,
        ): WebsiteSetting {
            $setting = WebsiteSetting::query()->lockForUpdate()->first();
            $currentRevision = $setting?->revision ?? 0;

            if ($currentRevision !== $expectedRevision) {
                throw new WebsiteSettingRevisionConflict;
            }

            $beforeSnapshot = $setting instanceof WebsiteSetting
                ? WebsiteSettings::fromModel($setting)->toPersistenceArray()
                : WebsiteSettings::defaults()->toPersistenceArray();
            $nextRevision = $currentRevision + 1;
            $attributes = [
                ...$settings->toPersistenceArray(),
                'revision' => $nextRevision,
                'updated_by_administrator_id' => $administrator->getKey(),
            ];

            if ($setting instanceof WebsiteSetting) {
                $setting->update($attributes);
            } else {
                $setting = WebsiteSetting::query()->create($attributes);
            }

            WebsiteSettingRevision::query()->create([
                'website_setting_id' => $setting->getKey(),
                'revision' => $nextRevision,
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $settings->toPersistenceArray(),
                'changed_by_administrator_id' => $administrator->getKey(),
            ]);

            return $setting->refresh();
        });

        $this->settingsManager->forget();

        return $setting;
    }
}
