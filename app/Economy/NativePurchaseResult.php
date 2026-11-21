<?php

namespace App\Economy;

/**
 * Carries exact native before/after balances or membership values for audit and replay.
 */
final readonly class NativePurchaseResult
{
    /**
     * Retains actual persisted values, including native server-local membership dates.
     *
     * @param  array<string, int|string|null>  $before
     * @param  array<string, int|string|null>  $after
     */
    public function __construct(public array $before, public array $after) {}
}
