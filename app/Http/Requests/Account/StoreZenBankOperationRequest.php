<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\ZenBank\ZenBankOperationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one replay-safe Zen Bank transfer request.
 */
final class StoreZenBankOperationRequest extends FormRequest
{
    /**
     * Confirms a game account owns the authenticated session.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Returns transfer direction, amount, character and replay-key constraints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'character_name' => ['required', 'string', 'between:1,10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'type' => ['required', Rule::enum(ZenBankOperationType::class)],
            'amount' => ['required', 'integer', 'between:1,2000000000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
