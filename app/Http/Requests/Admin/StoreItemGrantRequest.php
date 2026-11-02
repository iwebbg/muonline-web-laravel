<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one catalog-backed administrator item grant.
 */
final class StoreItemGrantRequest extends FormRequest
{
    /**
     * Allows only administrators with the granular item-grant capability.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::GrantItems);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'string',
                'between:4,10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'item_code' => ['required', 'string', 'regex:/\A(?:[0-9]|1[0-5]):(?:[0-9]|[12][0-9]|3[01])\z/'],
            'group' => ['required', 'integer', 'between:0,15'],
            'index' => ['required', 'integer', 'between:0,31'],
            'level' => ['required', 'integer', 'between:0,15'],
            'durability' => ['required', 'integer', 'between:0,255'],
            'option_level' => ['required', 'integer', 'between:0,7'],
            'has_skill' => ['nullable', 'boolean'],
            'has_luck' => ['nullable', 'boolean'],
            'excellent_mask' => ['required', 'integer', Rule::in([0])],
            'ancient_option' => ['required', 'integer', Rule::in([0])],
            'reason' => ['required', 'string', 'between:5,500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * Splits the allow-listed catalog code into its binary identity fields.
     */
    protected function prepareForValidation(): void
    {
        $itemCode = $this->string('item_code')->toString();
        $parts = explode(':', $itemCode, 2);

        $this->merge([
            'group' => $parts[0] ?? null,
            'index' => $parts[1] ?? null,
        ]);
    }

    /**
     * Returns normalized item options for the version-specific item factory.
     *
     * @return array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}
     */
    public function itemOptions(): array
    {
        return [
            'group' => $this->integer('group'),
            'index' => $this->integer('index'),
            'level' => $this->integer('level'),
            'durability' => $this->integer('durability'),
            'option_level' => $this->integer('option_level'),
            'has_skill' => $this->boolean('has_skill'),
            'has_luck' => $this->boolean('has_luck'),
            'excellent_mask' => $this->integer('excellent_mask'),
            'ancient_option' => $this->integer('ancient_option'),
        ];
    }
}
