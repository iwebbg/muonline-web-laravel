<?php

namespace App\Database\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use RuntimeException;

/**
 * Persists arbitrary binary bytes without passing them through SQL Server's
 * Unicode string parameter conversion.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class SqlServerBinary implements CastsAttributes
{
    /**
     * Returns the exact bytes supplied by the database driver.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $bytes = stream_get_contents($value);

            if ($bytes === false) {
                throw new RuntimeException("Unable to read the {$key} binary stream.");
            }

            return $bytes;
        }

        if ($value instanceof Expression) {
            $literal = $value->getValue($model->getConnection()->getQueryGrammar());

            if (is_string($literal) && str_starts_with($literal, '0x')) {
                $bytes = hex2bin(substr($literal, 2));

                if ($bytes !== false) {
                    return $bytes;
                }
            }
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The {$key} attribute must contain binary string data.");
        }

        return $value;
    }

    /**
     * Uses a SQL Server varbinary literal so the driver never interprets raw
     * item bytes as an nvarchar parameter.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException("The {$key} attribute must contain binary string data.");
        }

        if ($model->getConnection()->getDriverName() !== 'sqlsrv') {
            return $value;
        }

        return new Expression('0x'.strtoupper(bin2hex($value)));
    }
}
