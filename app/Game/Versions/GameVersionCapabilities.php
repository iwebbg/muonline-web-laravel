<?php

namespace App\Game\Versions;

use App\Modules\ServerModule;
use App\Reset\InventoryPolicy;
use Illuminate\Support\Str;

/**
 * Declares optional game-schema features without probing legacy tables at runtime.
 */
final readonly class GameVersionCapabilities
{
    /**
     * Creates one immutable set of version-owned persistence capabilities.
     *
     * @param  list<InventoryPolicy>|null  $resetInventoryPolicies  Reviewed inventory preservation modes; null retains all existing modes.
     * @param  list<ServerModule>|null  $availableModules  Supported modules for a staged adapter; null retains the complete legacy bundle.
     * @param  list<string>|null  $availableRoutes  Reviewed route names for a staged adapter; null permits the complete website.
     */
    public function __construct(
        public bool $grandResets,
        public bool $characterVip,
        public bool $billingRecord,
        public bool $itemGenerator = true,
        public bool $castleSiege = false,
        public bool $masterProgression = false,
        public bool $gens = false,
        public bool $luckyCoin = false,
        public bool $readOnly = false,
        public ?array $availableRoutes = null,
        public ?array $availableModules = null,
        public ?array $resetInventoryPolicies = null,
        public ?string $schemaExtensionResource = null,
    ) {}

    /**
     * Reports whether a named endpoint belongs to the reviewed adapter surface.
     */
    public function allowsRoute(string $routeName): bool
    {
        if ($this->availableRoutes === null) {
            return true;
        }

        foreach ($this->availableRoutes as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
