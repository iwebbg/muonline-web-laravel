<?php

namespace App\Installation\Contracts;

use App\Installation\ApplicationDatabaseRemoval;
use App\Installation\ApplicationDatabaseRemovalResult;

/**
 * Removes only a verified application database and its unshared runtime login.
 */
interface ApplicationDatabaseUninstaller
{
    /**
     * Performs preflight checks before destructive statements and reports partial completion.
     */
    public function uninstall(ApplicationDatabaseRemoval $removal): ApplicationDatabaseRemovalResult;
}
