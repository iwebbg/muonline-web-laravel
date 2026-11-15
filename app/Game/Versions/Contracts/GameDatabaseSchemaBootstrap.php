<?php

namespace App\Game\Versions\Contracts;

/**
 * Declares an idempotent schema extension needed after runtime principals exist and before grants.
 */
interface GameDatabaseSchemaBootstrap
{
    /**
     * Returns the trusted resource path containing this adapter's bootstrap extension.
     */
    public function bootstrapSchemaExtensionResource(): string;
}
