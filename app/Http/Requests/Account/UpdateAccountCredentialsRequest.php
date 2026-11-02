<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Authorizes and validates one authenticated legacy credential update.
 */
final class UpdateAccountCredentialsRequest extends FormRequest
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'currentPassword' => [
                'required',
                'string',
                'max:10',
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:50',
            ],
            'password' => [
                'nullable',
                'string',
                'min:4',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
                'confirmed',
            ],
        ];
    }

    /**
     * Returns localized messages for the legacy credential boundary.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currentPassword.required' => __('ui.account.credentials_current_password_required'),
            'currentPassword.max' => __('ui.account.credentials_current_password_invalid'),
            'email.required' => __('ui.account.credentials_email_required'),
            'email.email' => __('ui.account.credentials_email_invalid'),
            'email.max' => __('ui.account.credentials_email_length'),
            'password.min' => __('ui.account.credentials_password_length'),
            'password.max' => __('ui.account.credentials_password_length'),
            'password.regex' => __('ui.account.credentials_legacy_characters'),
            'password.confirmed' => __('ui.account.credentials_password_confirmation'),
        ];
    }

    /**
     * Normalizes the non-secret email field before validation and persistence.
     */
    protected function prepareForValidation(): void
    {
        $email = Str::lower(trim($this->string('email')->toString()));

        $this->merge([
            'email' => $email,
        ]);
    }

    /**
     * Returns the validated current legacy password.
     */
    public function currentPassword(): string
    {
        return (string) $this->validated('currentPassword');
    }

    /**
     * Returns the validated normalized email address.
     */
    public function email(): string
    {
        return (string) $this->validated('email');
    }

    /**
     * Returns the optional validated replacement password.
     */
    public function newPassword(): ?string
    {
        $password = $this->validated('password');

        return is_string($password) && $password !== ''
            ? $password
            : null;
    }
}
