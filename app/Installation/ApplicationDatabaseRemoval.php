<?php

namespace App\Installation;

use SensitiveParameter;

/**
 * Carries an explicitly confirmed SQL target and temporary stdin-only credentials.
 */
final readonly class ApplicationDatabaseRemoval
{
    /**
     * Keeps credentials out of stack-trace arguments while retaining the exact requested target.
     */
    public function __construct(
        public string $host,
        public int $port,
        public string $databaseName,
        public string $loginName,
        public string $adminUsername,
        #[SensitiveParameter] public string $adminPassword,
    ) {}
}
