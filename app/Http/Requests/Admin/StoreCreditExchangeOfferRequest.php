<?php

namespace App\Http\Requests\Admin;

use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\Administrator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one exact catalog-backed Credits exchange offer.
 */
class StoreCreditExchangeOfferRequest extends FormRequest
{
    /**
     * Allows authenticated economy administrators through the request boundary.
     */
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Administrator;
    }

    /**
     * Restricts item options to values supported by the active game profile.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(ItemCatalog $catalog, ItemGrantRules $itemGrantRules): array
    {
        $itemCodes = array_map(
            static fn ($definition): string => $definition->code(),
            $catalog->allDefinitions(),
        );

        return [
            'item_code' => ['required', 'string', Rule::in($itemCodes)],
            'level' => ['required', 'integer', 'min:0', 'max:'.$itemGrantRules->maxItemLevel()],
            'option_level' => ['required', 'integer', 'min:0', 'max:'.$itemGrantRules->maxOptionLevel()],
            'has_skill' => ['required', 'boolean'],
            'has_luck' => ['required', 'boolean'],
            'excellent_mask' => ['required', 'integer', 'min:0', 'max:63'],
            'ancient_option' => ['required', 'integer', 'min:0', 'max:255'],
            'credits_per_item' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'max_quantity_per_exchange' => ['required', 'integer', 'min:1', 'max:100'],
            'daily_account_credit_limit' => ['required', 'integer', 'min:1', 'max:1000000000', 'gte:credits_per_item'],
            'enabled' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Converts validated input to trusted persistence attributes and catalog snapshots.
     *
     * @return array<string, bool|int|string|null>
     */
    public function offerData(ItemCatalog $catalog, GameVersionProfile $profile): array
    {
        $validated = $this->validated();
        [$group, $index] = array_map('intval', explode(':', (string) $validated['item_code'], 2));
        $definition = $catalog->definitionFor($group, $index);

        abort_if($definition === null, 422);

        return [
            'game_profile_key' => $profile->key(),
            'item_group' => $group,
            'item_index' => $index,
            'level' => (int) $validated['level'],
            'option_level' => (int) $validated['option_level'],
            'has_skill' => (bool) $validated['has_skill'],
            'has_luck' => (bool) $validated['has_luck'],
            'excellent_mask' => (int) $validated['excellent_mask'],
            'ancient_option' => (int) $validated['ancient_option'],
            'item_name_snapshot' => $definition->name,
            'item_image_snapshot' => $definition->image,
            'excellent_type' => $definition->excellentType,
            'option_type' => $definition->optionType,
            'credits_per_item' => (int) $validated['credits_per_item'],
            'max_quantity_per_exchange' => (int) $validated['max_quantity_per_exchange'],
            'daily_account_credit_limit' => (int) $validated['daily_account_credit_limit'],
            'enabled' => (bool) $validated['enabled'],
            'sort_order' => (int) $validated['sort_order'],
        ];
    }
}
