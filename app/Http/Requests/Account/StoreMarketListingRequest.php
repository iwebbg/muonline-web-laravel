<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one idempotent warehouse market listing request.
 */
final class StoreMarketListingRequest extends FormRequest
{
    /**
     * Confirms a game account owns the authenticated session.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Returns exact character, vault, slot, price and replay-key constraints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'character_name' => ['required', 'string', 'between:1,10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'vault' => ['required', 'integer', 'between:0,10'],
            'slot' => ['required', 'integer', 'between:0,119'],
            'price_credits' => ['required', 'integer', 'between:1,1000000000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
