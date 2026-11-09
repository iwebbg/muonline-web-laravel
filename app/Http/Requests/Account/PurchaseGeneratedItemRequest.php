<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Versions\Contracts\GameVersionProfile;
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
        return $this->user() instanceof GameAccount
            && app(GameVersionProfile::class)->capabilities()->itemGenerator;
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
            'item_code' => ['required', 'string', 'regex:/\A(?:[0-9]|1[0-5]):(?:[0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\z/'],
            'group' => ['required', 'integer', 'between:0,15'],
            'index' => ['required', 'integer', 'between:0,255'],
            'level' => ['required', 'integer', 'between:0,'.$itemGrantRules->maxItemLevel()],
            'durability' => ['required', 'integer', 'between:0,255'],
            'option_level' => ['required', 'integer', 'between:0,'.$itemGrantRules->maxOptionLevel()],
            'has_skill' => ['nullable', 'boolean'],
            'has_luck' => ['nullable', 'boolean'],
            'excellent_mask' => ['required', 'integer', 'between:0,63'],
            'ancient_option' => ['required', 'integer', 'in:0,5,10'],
            'harmony_option' => ['required', 'integer', 'between:0,254'],
            'socket_count' => ['required', 'integer', 'between:0,5'],
            'has_380_option' => ['nullable', 'boolean'],
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

                $quote = app(ItemGeneratorCatalog::class)->quote($this->itemOptions());

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
     * @return array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int, harmony_option: int, socket_count: int, has_380_option: bool}
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
            'harmony_option' => $this->integer('harmony_option'),
            'socket_count' => $this->integer('socket_count'),
            'has_380_option' => $this->boolean('has_380_option'),
        ];
    }
}
