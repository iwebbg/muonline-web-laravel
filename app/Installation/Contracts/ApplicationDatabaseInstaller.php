<?php

namespace App\Installation\Contracts;

use App\Installation\ApplicationDatabaseInstallation;

/**
 * Provisions the isolated application database through a temporary administrator connection.
 */
interface ApplicationDatabaseInstaller
{
    /**
     * Creates missing SQL Server resources without changing an existing login password.
     */
    public function install(ApplicationDatabaseInstallation $installation): void;
}
