<?php

namespace App\Website;

use App\Models\WebsiteSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the website settings once for the current application lifecycle.
 */
final class WebsiteSettingsManager
{
    private ?WebsiteSettings $resolvedSettings = null;

    /**
     * Returns the persisted website snapshot or reviewed defaults before migration.
     */
    public function current(): WebsiteSettings
    {
        if ($this->resolvedSettings instanceof WebsiteSettings) {
            return $this->resolvedSettings;
        }

        if (! Schema::hasTable('website_settings')) {
            return $this->resolvedSettings = WebsiteSettings::defaults();
        }

        $setting = WebsiteSetting::query()->first();

        return $this->resolvedSettings = $setting instanceof WebsiteSetting
            ? WebsiteSettings::fromModel($setting)
            : WebsiteSettings::defaults();
    }

    /**
     * Clears the lifecycle snapshot after an administrator publishes a revision.
     */
    public function forget(): void
    {
        $this->resolvedSettings = null;
    }
}
