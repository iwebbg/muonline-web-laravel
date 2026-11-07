<?php

namespace App\Game\Versions\Contracts;

/**
 * Defines the schema and least-privilege SQL access required by one game profile.
 */
interface GameDatabaseProfile
{
    /**
     * Returns the minimum legacy columns required by the profile's adapters.
     *
     * @return array<string, list<string>>
     */
    public function requiredDatabaseSchema(): array;

    /**
     * Returns the exact object permissions required by the profile's adapters.
     *
     * @return array<string, list<string>>
     */
    public function databaseObjectPermissions(): array;
}
