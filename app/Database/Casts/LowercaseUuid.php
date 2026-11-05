<?php

namespace App\Database\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Keeps UUID attributes canonical when SQL Server returns uppercase values.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class LowercaseUuid implements CastsAttributes
{
    /**
     * Returns a lowercase UUID for stable routes and equality checks.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->normalize($key, $value);
    }

    /**
     * Stores the canonical lowercase form accepted by every supported driver.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->normalize($key, $value);
    }

    /**
     * Normalizes one nullable UUID attribute.
     */
    private function normalize(string $key, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The {$key} attribute must contain a UUID string.");
        }

        return strtolower($value);
    }
}
