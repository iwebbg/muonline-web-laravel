<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes integer exchange policy updates with optimistic revision checks.
 */
final class UpdateGameCurrencyExchangeRateRequest extends FormRequest
{
    /**
     * Restricts rate changes to administrators allowed to manage the economy.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Keeps exchange ratios positive and quantities within explicit integer limits.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'credit_units' => ['required', 'integer', 'between:1,1000000000'],
            'coin_units' => ['required', 'integer', 'between:1,1000000000'],
            'minimum_units' => ['required', 'integer', 'between:1,1000000'],
            'maximum_units' => ['required', 'integer', 'between:1,1000000', 'gte:minimum_units'],
            'enabled' => ['nullable', 'boolean'], 'revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
