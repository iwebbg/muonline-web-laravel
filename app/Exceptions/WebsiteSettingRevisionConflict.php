<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Indicates that a stale website settings form cannot overwrite a newer revision.
 */
final class WebsiteSettingRevisionConflict extends RuntimeException
{
    /**
     * Creates the conflict with a stable internal explanation.
     */
    public function __construct()
    {
        parent::__construct('The website settings revision changed before this update.');
    }
}
