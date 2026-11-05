<?php

namespace App\Shop\Exceptions;

use RuntimeException;

/**
 * Rejects an administrative reward grant without exposing legacy details.
 */
final class RewardGrantRejected extends RuntimeException {}
