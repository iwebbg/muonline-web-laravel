<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Restricts native S21 warehouse inspection to permissioned administrators and validated targets.
 */
final class InspectMuDevsSeason21WarehouseRequest extends FormRequest
{
    /**
     * Allows administrators with settings access to inspect the commissioning connection.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageSettings);
    }

    /**
     * Limits the account identifier and exact native extended-vault number.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account' => ['nullable', 'string', 'max:10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'vault' => ['nullable', 'integer', 'between:0,2147483647'],
        ];
    }
}
