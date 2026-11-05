<?php

namespace App\Installation;

/**
 * Carries the isolated SQL Server database and runtime principal to provision.
 */
final readonly class ApplicationDatabaseInstallation
{
    /**
     * Creates an immutable application-database installation request.
     */
    public function __construct(
        public string $databaseName,
        public string $loginName,
        public string $loginPassword,
        public string $roleName,
    ) {}
}
