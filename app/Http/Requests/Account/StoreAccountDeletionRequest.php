<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates current-password confirmation before issuing a deletion code.
 */
final class StoreAccountDeletionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'max:10'],
        ];
    }

    /**
     * Returns the sensitive current password only to the verification controller.
     */
    public function currentPassword(): string
    {
        return (string) $this->validated('currentPassword');
    }
}
