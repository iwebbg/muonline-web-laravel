<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates one token-backed legacy-compatible game password replacement.
 */
final class ResetGameAccountPasswordRequest extends FormRequest
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
            'account' => [
                'required',
                'string',
                'min:4',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'token' => ['required', 'string', 'size:64', 'alpha_num:ascii'],
            'password' => [
                'required',
                'string',
                'min:4',
                'max:10',
                'confirmed',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
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
     * Returns the validated single-use token supplied by the email link.
     */
    public function resetToken(): string
    {
        return (string) $this->validated('token');
    }

    /**
     * Returns the validated password required by the active game profile.
     */
    public function newPassword(): string
    {
        return (string) $this->validated('password');
    }
}
