<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates creation of one character VIP plan.
 */
class StoreVipPlanRequest extends FormRequest
{
    /**
     * Confirms the administrator may manage the server economy.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Returns the validated VIP plan policy fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('vip_plans', 'slug'),
            ],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bg' => ['nullable', 'string', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_bg' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'between:1,3650'],
            'price_credits' => ['required', 'integer', 'between:1,1000000000'],
            'enabled' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
        ];
    }

    /**
     * Returns normalized attributes accepted by the VIP plan model.
     *
     * @return array<string, mixed>
     */
    public function planData(): array
    {
        return [
            ...$this->safe()->except('enabled'),
            'enabled' => $this->boolean('enabled'),
        ];
    }
}
