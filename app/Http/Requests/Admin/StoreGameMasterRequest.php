<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Authorizes and validates creation of one permission-scoped Game Master.
 */
final class StoreGameMasterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->role === AdministratorRole::Administrator;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:administrators,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', Rule::enum(AdministratorPermission::class)],
        ];
    }

    /**
     * Returns validated identity fields without the password confirmation.
     *
     * @return array{name: string, email: string, password: string, permissions: list<string>}
     */
    public function identityData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
            'permissions' => array_values((array) $this->validated('permissions')),
        ];
    }
}
