<?php

namespace App\Admin;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects password bytes that bcrypt would truncate or cannot hash safely.
 */
final class AdministratorPasswordInput implements ValidationRule
{
    /**
     * Enforces the active hasher's byte boundary independently of Unicode character counts.
     *
     * @param  Closure(string): mixed  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (str_contains($value, "\0")) {
            $fail(__('ui.admin_auth.password_null_byte'));
        }

        if (config('hashing.driver', 'bcrypt') === 'bcrypt' && strlen($value) > 72) {
            $fail(__('ui.admin_auth.password_byte_limit'));
        }
    }
}
