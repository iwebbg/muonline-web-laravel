<?php

namespace App\Http\Requests\Admin;

use App\Models\VipPlan;
use Illuminate\Validation\Rule;

/**
 * Validates updates to one existing character VIP plan.
 */
final class UpdateVipPlanRequest extends StoreVipPlanRequest
{
    /**
     * Returns creation rules with the current plan excluded from slug uniqueness.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $plan = $this->route('vipPlan');

        if ($plan instanceof VipPlan) {
            $rules['slug'] = [
                'required',
                'string',
                'max:80',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('vip_plans', 'slug')->ignore($plan),
            ];
        }

        return $rules;
    }
}
