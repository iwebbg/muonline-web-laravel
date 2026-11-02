<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one idempotent character VIP purchase request.
 */
final class PurchaseVipRequest extends FormRequest
{
    /**
     * Confirms a game account owns the authenticated session.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Returns the accepted plan, character and replay-key fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'character_name' => [
                'required',
                'string',
                'between:1,10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'vip_plan_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
