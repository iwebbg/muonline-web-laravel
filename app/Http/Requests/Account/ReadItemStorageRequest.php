<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an authenticated character or account-vault storage request.
 */
final class ReadItemStorageRequest extends FormRequest
{
    /**
     * Allows only authenticated MuOnline account identities.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'characterName' => ['nullable', 'string', 'max:10'],
        ];
    }
}
