<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\WebStorage\ItemStorageLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one exact physical jewel deposit request.
 */
final class StoreJewelRequest extends FormRequest
{
    /**
     * Confirms a game account owns the authenticated session.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Returns exact source, jewel quantity and replay-key constraints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'character_name' => ['required', 'string', 'between:1,10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'source_location' => ['required', Rule::enum(ItemStorageLocation::class)],
            'vault' => ['required', 'integer', 'between:0,10'],
            'jewel_code' => ['required', 'string', 'regex:/\A\d{1,2}:\d{1,2}\z/'],
            'jewel_level' => ['required', 'integer', 'between:0,15'],
            'quantity' => ['required', 'integer', 'between:1,120'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
