<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemGrantRules97d99i;
use App\Models\Administrator;
use App\Shop\ShopClassScope;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Authorizes and validates one catalog-backed Web Shop offer.
 */
class StoreShopOfferRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ShopOfferType::class)],
            'class_scope' => ['required', Rule::enum(ShopClassScope::class)],
            'title_en' => ['required', 'string', 'max:120'],
            'title_bg' => ['required', 'string', 'max:120'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'description_bg' => ['nullable', 'string', 'max:2000'],
            'price_credits' => ['required', 'integer', 'between:1,9000000000000000'],
            'status' => ['required', Rule::enum(ShopOfferStatus::class)],
            'sort_order' => ['required', 'integer', 'between:0,1000000'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
            'items' => ['required', 'array', 'between:1,50'],
            'items.*.item_code' => ['required', 'string', 'regex:/\A(?:[0-9]|1[0-5]):(?:[0-9]|[12][0-9]|3[01])\z/'],
            'items.*.level' => ['required', 'integer', 'between:0,'.ItemGrantRules97d99i::MAX_ITEM_LEVEL],
            'items.*.durability' => ['required', 'integer', 'between:0,255'],
            'items.*.option_level' => ['required', 'integer', 'between:0,'.ItemGrantRules97d99i::MAX_OPTION_LEVEL],
            'items.*.has_skill' => ['nullable', 'boolean'],
            'items.*.has_luck' => ['nullable', 'boolean'],
            'items.*.excellent_mask' => ['required', 'integer', 'between:0,63'],
            'items.*.ancient_option' => ['required', 'integer', 'in:0'],
            'items.*.weight' => ['required', 'integer', 'between:1,1000000'],
        ];
    }

    /**
     * Validates item identities and the single-item offer invariant.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->input('items', []);
                $type = ShopOfferType::tryFrom($this->string('type')->toString());

                if ($type === ShopOfferType::Item && is_array($items) && count($items) !== 1) {
                    $validator->errors()->add('items', __('ui.admin.shop_item_requires_one_reward'));
                }

                if (! is_array($items)) {
                    return;
                }

                $catalog = app(ItemCatalog97d99i::class);

                foreach ($items as $index => $item) {
                    if (! is_array($item) || ! isset($item['item_code'])) {
                        continue;
                    }

                    [$group, $itemIndex] = array_pad(explode(':', (string) $item['item_code'], 2), 2, null);

                    if ($catalog->definitionFor((int) $group, (int) $itemIndex) === null) {
                        $validator->errors()->add('items.'.$index.'.item_code', __('ui.admin.shop_unknown_item'));
                    }
                }
            },
        ];
    }

    /**
     * Returns normalized offer fields excluding the nested reward pool.
     *
     * @return array<string, mixed>
     */
    public function offerData(): array
    {
        $data = $this->safe()->except(['items']);
        $data['published_at'] = $data['status'] === ShopOfferStatus::Published->value ? now() : null;

        return $data;
    }

    /**
     * Resolves submitted item identities to trusted catalog metadata.
     *
     * @return list<array<string, int|string|bool|null>>
     */
    public function itemData(): array
    {
        $catalog = app(ItemCatalog97d99i::class);
        $items = [];

        foreach ($this->validated('items') as $sortOrder => $item) {
            [$group, $index] = array_map('intval', explode(':', $item['item_code'], 2));
            $definition = $catalog->definitionFor($group, $index);

            if ($definition === null) {
                continue;
            }

            $items[] = [
                'item_code' => $definition->code(),
                'item_name' => $definition->name,
                'item_image' => $definition->image,
                'item_width' => $definition->width,
                'item_height' => $definition->height,
                'level' => (int) $item['level'],
                'durability' => (int) $item['durability'],
                'option_level' => (int) $item['option_level'],
                'has_skill' => filter_var($item['has_skill'] ?? false, FILTER_VALIDATE_BOOL),
                'has_luck' => filter_var($item['has_luck'] ?? false, FILTER_VALIDATE_BOOL),
                'excellent_mask' => (int) $item['excellent_mask'],
                'ancient_option' => (int) $item['ancient_option'],
                'weight' => (int) $item['weight'],
                'sort_order' => $sortOrder,
            ];
        }

        return $items;
    }
}
