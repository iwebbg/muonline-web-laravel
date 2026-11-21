<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\Economy\NativeCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates integer exchange packs and the optional owned-character Ruud target.
 */
final class ExchangeGameCurrencyRequest extends FormRequest
{
    /**
     * Requires an authenticated native game account.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Accepts only declared currencies, public quantities and a durable request identity.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'currency' => ['required', Rule::enum(NativeCurrency::class)],
            'units' => ['required', 'integer', 'between:1,1000000'],
            'character_name' => ['required_if:currency,ruud', 'nullable', 'string', 'between:1,10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
