<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates a reset preview for the current game account.
 */
final class PreviewCharacterResetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'characterName' => [
                'required',
                'string',
                'min:3',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
        ];
    }

    /**
     * Returns the validated legacy character identifier.
     */
    public function characterName(): string
    {
        return (string) $this->validated('characterName');
    }
}
