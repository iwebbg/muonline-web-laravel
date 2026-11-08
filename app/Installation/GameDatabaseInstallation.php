<?php

namespace App\Installation;

/**
 * Carries an existing game database and restricted runtime principal to provision.
 */
final readonly class GameDatabaseInstallation
{
    /**
     * Creates an immutable game-database installation request.
     */
    public function __construct(
        public string $databaseName,
        public string $loginName,
        public string $loginPassword,
        public string $roleName,
    ) {}
}
