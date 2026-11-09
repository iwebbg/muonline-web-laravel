<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one paid character reset package.
 */
final class PurchaseCharacterResetsRequest extends FormRequest
{
    /**
     * Allows authenticated game accounts through the request boundary.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Accepts only public package multipliers and legacy-safe character names.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'character_name' => ['required', 'string', 'between:1,10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'package_count' => ['required', 'integer', Rule::in([1, 5, 10])],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
