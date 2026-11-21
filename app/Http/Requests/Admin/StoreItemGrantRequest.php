<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\ItemConstructionPolicy;
use App\Game\Contracts\ItemGrantRules;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        $itemGrantRules = app(ItemGrantRules::class);

        return [
            'account_id' => [
                'required',
                'string',
                'between:4,10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'item_code' => ['required', 'string', 'regex:/\A(?:[0-9]|1[0-5]):(?:0|[1-9][0-9]{0,2})\z/'],
            'group' => ['required', 'integer', 'between:0,15'],
            'index' => ['required', 'integer', 'between:0,'.$itemGrantRules->maxItemIndex()],
            'level' => ['required', 'integer', 'between:0,'.$itemGrantRules->maxItemLevel()],
            'durability' => ['required', 'integer', 'between:0,255'],
            'option_level' => ['required', 'integer', 'between:0,'.$itemGrantRules->maxOptionLevel()],
            'has_skill' => ['nullable', 'boolean'],
            'has_luck' => ['nullable', 'boolean'],
            'excellent_mask' => ['required', 'integer', 'between:0,63'],
            'ancient_option' => ['required', 'integer', $itemGrantRules instanceof ItemConstructionPolicy ? 'between:0,15' : 'in:0'],
            'harmony_option' => ['sometimes', 'integer', 'between:0,254'],
            'socket_count' => ['sometimes', 'integer', 'between:0,5'],
            'has_380_option' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'between:5,500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * Validates advanced combinations against the selected adapter before reserving serials.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rules = app(ItemGrantRules::class);
            $options = $this->itemOptions();
            $hasAdvanced = $this->integer('ancient_option') !== 0
                || $this->integer('harmony_option') !== 0
                || $this->integer('socket_count') !== 0 || $this->boolean('has_380_option');
            $allowed = $rules instanceof ItemConstructionPolicy
                ? $rules->allowsConstruction($options)
                : ! $hasAdvanced;

            if (! $allowed) {
                $validator->errors()->add('item_code', __('ui.admin.item_grant_options_invalid'));
            }
        }];
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
     * @return array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int, harmony_option?: int, socket_count?: int, has_380_option?: bool}
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
            ...array_filter([
                'harmony_option' => $this->integer('harmony_option'),
                'socket_count' => $this->integer('socket_count'),
                'has_380_option' => $this->boolean('has_380_option'),
            ]),
        ];
    }
}
