<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the account-owned deletion request and its six-digit email code.
 */
final class ConfirmAccountDeletionRequest extends FormRequest
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
            'deletionRequest' => ['required', 'integer', 'min:1'],
            'verificationCode' => ['required', 'string', 'digits:6'],
        ];
    }

    /**
     * Returns the local deletion-request identifier.
     */
    public function deletionRequestId(): int
    {
        return (int) $this->validated('deletionRequest');
    }

    /**
     * Returns the submitted short-lived verification code.
     */
    public function verificationCode(): string
    {
        return (string) $this->validated('verificationCode');
    }
}
