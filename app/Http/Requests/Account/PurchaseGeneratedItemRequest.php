<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\Game\Versions\Version97d99i\ItemGeneratorCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemGrantRules97d99i;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Authorizes and validates one server-priced custom item purchase.
 */
final class PurchaseGeneratedItemRequest extends FormRequest
{
    /**
     * Allows only the authenticated game account to purchase its own item.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'regex:/\A(?:[0-9]|1[0-5]):(?:[0-9]|[12][0-9]|3[01])\z/'],
            'group' => ['required', 'integer', 'between:0,15'],
            'index' => ['required', 'integer', 'between:0,31'],
            'level' => ['required', 'integer', 'between:0,'.ItemGrantRules97d99i::MAX_ITEM_LEVEL],
            'durability' => ['required', 'integer', 'between:0,255'],
            'option_level' => ['required', 'integer', 'between:0,'.ItemGrantRules97d99i::MAX_OPTION_LEVEL],
            'has_skill' => ['nullable', 'boolean'],
            'has_luck' => ['nullable', 'boolean'],
            'excellent_mask' => ['required', 'integer', 'between:0,63'],
            'ancient_option' => ['required', 'integer', 'in:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * Rejects options unavailable for the exact version-owned item definition.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $quote = app(ItemGeneratorCatalog97d99i::class)->quote($this->itemOptions());

                if ($quote === null) {
                    $validator->errors()->add('item_code', __('ui.shop.generator_invalid_item'));
                }
            },
        ];
    }

    /**
     * Splits the allow-listed catalog code into its binary identity fields.
     */
    protected function prepareForValidation(): void
    {
        $parts = explode(':', $this->string('item_code')->toString(), 2);

        $this->merge([
            'group' => $parts[0] ?? null,
            'index' => $parts[1] ?? null,
        ]);
    }

    /**
     * Returns normalized item options for pricing and item construction.
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
