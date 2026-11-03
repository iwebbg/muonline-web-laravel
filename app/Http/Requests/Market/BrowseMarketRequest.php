<?php

namespace App\Http\Requests\Market;

use App\Market\MarketItemCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the public item-name and category market filters.
 */
final class BrowseMarketRequest extends FormRequest
{
    /**
     * Allows every visitor to browse the public market.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Returns the constrained public market filter fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', Rule::enum(MarketItemCategory::class)],
        ];
    }

    /**
     * Returns the normalized item-name fragment or null when no search is active.
     */
    public function searchTerm(): ?string
    {
        $searchTerm = trim($this->string('q')->toString());

        return $searchTerm === '' ? null : $searchTerm;
    }

    /**
     * Returns the selected item category or null when all categories are visible.
     */
    public function category(): ?MarketItemCategory
    {
        $category = $this->string('category')->toString();

        return $category === '' ? null : MarketItemCategory::from($category);
    }
}
