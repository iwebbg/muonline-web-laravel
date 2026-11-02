<?php

namespace App\Exceptions\Modules;

use RuntimeException;

/**
 * Signals that an administrator submitted an outdated module revision.
 */
final class ModuleProfileRevisionConflict extends RuntimeException {}
