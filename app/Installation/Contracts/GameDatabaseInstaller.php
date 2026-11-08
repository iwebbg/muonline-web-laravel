<?php

namespace App\Installation\Contracts;

use App\Installation\GameDatabaseInstallation;

/**
 * Provisions restricted web access to an existing game database.
 */
interface GameDatabaseInstaller
{
    /**
     * Creates missing SQL Server principals and exact adapter permissions idempotently.
     */
    public function install(GameDatabaseInstallation $installation): void;
}
