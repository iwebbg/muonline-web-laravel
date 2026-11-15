<?php

namespace App\Game\Versions\Contracts;

/**
 * Identifies reviewed native objects that may be absent in otherwise compatible server installations.
 */
interface OptionalGameDatabaseObjects
{
    /**
     * Returns qualified objects whose grants are skipped only when the object does not exist.
     *
     * @return list<string>
     */
    public function optionalDatabaseObjects(): array;
}
