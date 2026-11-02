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
 * Authorizes and validates one Game Master permission or identity update.
 */
final class UpdateGameMasterRequest extends FormRequest
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
        $gameMaster = $this->route('gameMaster');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('administrators', 'email')->ignore(
                    $gameMaster instanceof Administrator ? $gameMaster->getKey() : null,
                ),
            ],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', Rule::enum(AdministratorPermission::class)],
        ];
    }

    /**
     * Returns validated identity fields and omits an unchanged password.
     *
     * @return array<string, mixed>
     */
    public function identityData(): array
    {
        $data = [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'is_active' => (bool) $this->validated('is_active'),
            'permissions' => array_values((array) $this->validated('permissions')),
        ];
        $password = $this->validated('password');

        if (is_string($password) && $password !== '') {
            $data['password'] = $password;
        }

        return $data;
    }
}
