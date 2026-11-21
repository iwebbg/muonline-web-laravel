<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a native account VIP purchase without accepting caller-supplied prices or tiers.
 */
final class PurchaseGameVipRequest extends FormRequest
{
    /**
     * Requires an authenticated native game account.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Resolves the actual offer and captured terms inside the purchase action.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['plan_id' => ['required', 'integer', 'min:1'], 'idempotency_key' => ['required', 'uuid']];
    }
}
