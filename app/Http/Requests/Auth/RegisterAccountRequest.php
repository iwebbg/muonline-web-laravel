<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates one guest request against the verified legacy account limits.
 */
final class RegisterAccountRequest extends FormRequest
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:4',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:50',
            ],
            'password' => [
                'required',
                'string',
                'min:4',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
                'confirmed',
            ],
            'recaptcha_token' => [
                config('services.recaptcha.enabled', false) ? 'required' : 'nullable',
                'string',
                'max:4096',
            ],
        ];
    }

    /**
     * Returns localized messages for the legacy-specific validation boundary.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => __('ui.registration.username_required'),
            'username.min' => __('ui.registration.username_length'),
            'username.max' => __('ui.registration.username_length'),
            'username.regex' => __('ui.registration.legacy_characters'),
            'email.required' => __('ui.registration.email_required'),
            'email.email' => __('ui.registration.email_invalid'),
            'email.max' => __('ui.registration.email_length'),
            'password.required' => __('ui.registration.password_required'),
            'password.min' => __('ui.registration.password_length'),
            'password.max' => __('ui.registration.password_length'),
            'password.regex' => __('ui.registration.legacy_characters'),
            'password.confirmed' => __('ui.registration.password_confirmation'),
            'recaptcha_token.required' => __('ui.registration.recaptcha_failed'),
        ];
    }

    /**
     * Normalizes non-secret identity fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $username = trim($this->string('username')->toString());
        $email = Str::lower(trim($this->string('email')->toString()));

        $this->merge([
            'username' => $username,
            'email' => $email,
        ]);
    }

    /**
     * Returns the validated legacy account identifier.
     */
    public function username(): string
    {
        return (string) $this->validated('username');
    }

    /**
     * Returns the validated email address.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /**
     * Returns the validated plaintext required by the 97d+99i game server.
     */
    public function password(): string
    {
        return (string) $this->validated('password');
    }

    /**
     * Returns the short-lived reCAPTCHA token supplied for this form submission.
     */
    public function recaptchaToken(): string
    {
        return (string) $this->validated('recaptcha_token', '');
    }
}
