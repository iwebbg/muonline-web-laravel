<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Payments\CreditPackageStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditPackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:120'],
            'title_bg' => ['required', 'string', 'max:120'],
            'credits' => ['required', 'integer', 'between:1,9000000000000000'],
            'price' => ['required', 'decimal:0,2', 'between:0.01,999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'status' => ['required', Rule::enum(CreditPackageStatus::class)],
            'sort_order' => ['required', 'integer', 'between:0,1000000'],
        ];
    }

    /** @return array<string, mixed> */
    public function packageData(): array
    {
        return [
            'title_en' => $this->string('title_en')->trim()->toString(),
            'title_bg' => $this->string('title_bg')->trim()->toString(),
            'credits' => $this->integer('credits'),
            'price_minor' => (int) round($this->float('price') * 100),
            'currency' => strtoupper($this->string('currency')->toString()),
            'status' => $this->string('status')->toString(),
            'sort_order' => $this->integer('sort_order'),
        ];
    }
}
