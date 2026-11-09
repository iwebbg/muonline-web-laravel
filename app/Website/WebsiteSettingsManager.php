<?php

namespace App\Website;

use App\Models\WebsiteSetting;
use App\Servers\CurrentGameServer;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the website settings once for the current application lifecycle.
 */
final class WebsiteSettingsManager
{
    private ?WebsiteSettings $resolvedSettings = null;

    public function __construct(
        private readonly CurrentGameServer $currentGameServer,
    ) {}

    /**
     * Returns the persisted website snapshot or reviewed defaults before migration.
     */
    public function current(): WebsiteSettings
    {
        if ($this->resolvedSettings instanceof WebsiteSettings) {
            return $this->resolvedSettings;
        }

        if (! Schema::hasTable('website_settings')) {
            return $this->resolvedSettings = WebsiteSettings::defaults(
                $this->currentGameServer->version(),
            );
        }

        $setting = WebsiteSetting::query()->first();

        return $this->resolvedSettings = $setting instanceof WebsiteSetting
            ? WebsiteSettings::fromModel($setting, $this->currentGameServer->version())
            : WebsiteSettings::defaults($this->currentGameServer->version());
    }

    /**
     * Clears the lifecycle snapshot after an administrator publishes a revision.
     */
    public function forget(): void
    {
        $this->resolvedSettings = null;
    }
}
