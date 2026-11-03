<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\WebStorage\ItemStorageLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one grouped Jewel Bank withdrawal request.
 */
final class WithdrawJewelBatchRequest extends FormRequest
{
    /**
     * Confirms a game account owns the authenticated session.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Returns exact jewel identity and requested quantity constraints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jewel_code' => ['required', 'string', 'regex:/\A\d{1,2}:\d{1,2}\z/'],
            'jewel_level' => ['required', 'integer', 'between:0,15'],
            'quantity' => ['required', 'integer', 'between:1,120'],
            'destination_location' => ['required', Rule::enum(ItemStorageLocation::class)],
            'character_name' => [
                Rule::requiredIf($this->string('destination_location')->toString() === ItemStorageLocation::Inventory->value),
                'nullable',
                'string',
                'between:1,10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
        ];
    }
}
