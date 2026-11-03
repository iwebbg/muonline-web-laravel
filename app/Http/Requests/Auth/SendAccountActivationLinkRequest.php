<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an activation resend destination without performing account discovery.
 */
final class SendAccountActivationLinkRequest extends FormRequest
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
            'account' => ['required', 'string', 'min:4', 'max:10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'email' => ['required', 'string', 'email:rfc', 'max:50'],
        ];
    }

    /**
     * Returns the validated legacy account identifier.
     */
    public function accountId(): string
    {
        return (string) $this->validated('account');
    }

    /**
     * Returns the validated activation email destination.
     */
    public function emailAddress(): string
    {
        return (string) $this->validated('email');
    }
}
