<?php

namespace App\Http\Requests\Admin;

use App\GrandReset\GrandResetPointsMode;
use App\Models\Administrator;
use App\PkClear\PkClearPriceMode;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileReader;
use App\Reset\ResetProfileSettings;
use App\Reset\ResetRewardType;
use App\Shop\ShopOfferType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates only the character-rule section selected by the route.
 */
final class UpdateCharacterRuleProfileRequest extends FormRequest
{
    /**
     * Allows authenticated administrators through the request boundary.
     */
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Administrator;
    }

    /**
     * Returns rules only for the focused character-rule form.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(ResetProfileReader $resetProfileReader): array
    {
        $current = $resetProfileReader->current();

        return match ($this->profile()) {
            'reset' => [
                'enabled' => ['required', 'boolean'],
                'requiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
                'maximumResets' => ['required', 'integer', 'min:'.max(1, $current->grandResetRequiredResets, $current->statResetRequiredResets), 'max:10000'],
                'maximumStat' => ['required', 'integer', 'min:1', 'max:32767'],
                'baseZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
                'multiplyCostByReset' => ['required', 'boolean'],
                'clearStats' => ['required', 'boolean'],
                'pointsMode' => ['required', Rule::enum(PointsMode::class)],
                'pointsPerReset' => ['required', 'integer', 'min:0', 'max:32767'],
                'inventoryPolicy' => ['required', Rule::enum(InventoryPolicy::class)],
                'resetRewardType' => ['required', Rule::enum(ResetRewardType::class)],
                'resetRewardCredits' => ['nullable', 'required_if:resetRewardType,credits', 'integer', 'min:1', 'max:9000000000000000'],
                'resetRewardShopOfferId' => [
                    'nullable',
                    'required_if:resetRewardType,mystery_box',
                    'integer',
                    Rule::exists('shop_offers', 'id')->where('type', ShopOfferType::MysteryBox->value),
                ],
            ],
            'grand-reset' => [
                'grandResetEnabled' => ['required', 'boolean'],
                'grandResetRequiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
                'grandResetRequiredResets' => ['required', 'integer', 'min:1', 'max:'.$current->maximumResets],
                'maximumGrandResets' => ['required', 'integer', 'min:1', 'max:10000'],
                'grandResetBaseZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
                'grandResetMultiplyCost' => ['required', 'boolean'],
                'grandResetClearStats' => ['required', 'boolean'],
                'grandResetPointsMode' => ['required', Rule::enum(GrandResetPointsMode::class)],
                'grandResetPoints' => ['required', 'integer', 'min:0', 'max:2000000000'],
                'grandResetInventoryPolicy' => ['required', Rule::enum(InventoryPolicy::class)],
                'grandResetConsumeRequiredResets' => ['required', 'boolean'],
                'grandResetRewardType' => ['required', Rule::enum(ResetRewardType::class)],
                'grandResetRewardCredits' => ['nullable', 'required_if:grandResetRewardType,credits', 'integer', 'min:1', 'max:9000000000000000'],
                'grandResetRewardShopOfferId' => [
                    'nullable',
                    'required_if:grandResetRewardType,mystery_box',
                    'integer',
                    Rule::exists('shop_offers', 'id')->where('type', ShopOfferType::MysteryBox->value),
                ],
            ],
            'stat-reset' => [
                'statResetEnabled' => ['required', 'boolean'],
                'statResetRequiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
                'statResetRequiredResets' => ['required', 'integer', 'min:0', 'max:'.$current->maximumResets],
                'statResetZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
            ],
            'pk-clear' => [
                'pkClearEnabled' => ['required', 'boolean'],
                'pkClearRequiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
                'pkClearBaseZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
                'pkClearPriceMode' => ['required', Rule::enum(PkClearPriceMode::class)],
            ],
            'move-to-town' => [
                'moveToTownEnabled' => ['required', 'boolean'],
                'moveToTownMap' => ['required', 'integer', 'min:0', 'max:255'],
                'moveToTownX' => ['required', 'integer', 'min:0', 'max:255'],
                'moveToTownY' => ['required', 'integer', 'min:0', 'max:255'],
                'moveToTownZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
            ],
            default => [],
        };
    }

    /**
     * Merges the validated section into the current typed active policy.
     */
    public function settings(ResetProfileSettings $current): ResetProfileSettings
    {
        $validated = $this->validated();
        $profile = $this->profile();

        return new ResetProfileSettings(
            enabled: $profile === 'reset' ? (bool) $validated['enabled'] : $current->enabled,
            requiredLevel: $profile === 'reset' ? (int) $validated['requiredLevel'] : $current->requiredLevel,
            maximumResets: $profile === 'reset' ? (int) $validated['maximumResets'] : $current->maximumResets,
            maximumStat: $profile === 'reset' ? (int) $validated['maximumStat'] : $current->maximumStat,
            grandResetRequiredResets: $profile === 'grand-reset' ? (int) $validated['grandResetRequiredResets'] : $current->grandResetRequiredResets,
            baseZenCost: $profile === 'reset' ? (int) $validated['baseZenCost'] : $current->baseZenCost,
            multiplyCostByReset: $profile === 'reset' ? (bool) $validated['multiplyCostByReset'] : $current->multiplyCostByReset,
            clearStats: $profile === 'reset' ? (bool) $validated['clearStats'] : $current->clearStats,
            pointsMode: $profile === 'reset' ? PointsMode::from((string) $validated['pointsMode']) : $current->pointsMode,
            pointsPerReset: $profile === 'reset' ? (int) $validated['pointsPerReset'] : $current->pointsPerReset,
            inventoryPolicy: $profile === 'reset' ? InventoryPolicy::from((string) $validated['inventoryPolicy']) : $current->inventoryPolicy,
            resetRewardType: $profile === 'reset' ? ResetRewardType::from((string) $validated['resetRewardType']) : $current->resetRewardType,
            resetRewardCredits: $profile === 'reset' ? (int) ($validated['resetRewardCredits'] ?? 0) : $current->resetRewardCredits,
            resetRewardShopOfferId: $profile === 'reset' ? $this->optionalInteger($validated, 'resetRewardShopOfferId') : $current->resetRewardShopOfferId,
            grandResetEnabled: $profile === 'grand-reset' ? (bool) $validated['grandResetEnabled'] : $current->grandResetEnabled,
            grandResetRequiredLevel: $profile === 'grand-reset' ? (int) $validated['grandResetRequiredLevel'] : $current->grandResetRequiredLevel,
            maximumGrandResets: $profile === 'grand-reset' ? (int) $validated['maximumGrandResets'] : $current->maximumGrandResets,
            grandResetBaseZenCost: $profile === 'grand-reset' ? (int) $validated['grandResetBaseZenCost'] : $current->grandResetBaseZenCost,
            grandResetMultiplyCost: $profile === 'grand-reset' ? (bool) $validated['grandResetMultiplyCost'] : $current->grandResetMultiplyCost,
            grandResetClearStats: $profile === 'grand-reset' ? (bool) $validated['grandResetClearStats'] : $current->grandResetClearStats,
            grandResetPointsMode: $profile === 'grand-reset' ? GrandResetPointsMode::from((string) $validated['grandResetPointsMode']) : $current->grandResetPointsMode,
            grandResetPoints: $profile === 'grand-reset' ? (int) $validated['grandResetPoints'] : $current->grandResetPoints,
            grandResetInventoryPolicy: $profile === 'grand-reset' ? InventoryPolicy::from((string) $validated['grandResetInventoryPolicy']) : $current->grandResetInventoryPolicy,
            grandResetConsumeRequiredResets: $profile === 'grand-reset' ? (bool) $validated['grandResetConsumeRequiredResets'] : $current->grandResetConsumeRequiredResets,
            grandResetRewardType: $profile === 'grand-reset' ? ResetRewardType::from((string) $validated['grandResetRewardType']) : $current->grandResetRewardType,
            grandResetRewardCredits: $profile === 'grand-reset' ? (int) ($validated['grandResetRewardCredits'] ?? 0) : $current->grandResetRewardCredits,
            grandResetRewardShopOfferId: $profile === 'grand-reset' ? $this->optionalInteger($validated, 'grandResetRewardShopOfferId') : $current->grandResetRewardShopOfferId,
            statResetEnabled: $profile === 'stat-reset' ? (bool) $validated['statResetEnabled'] : $current->statResetEnabled,
            statResetRequiredLevel: $profile === 'stat-reset' ? (int) $validated['statResetRequiredLevel'] : $current->statResetRequiredLevel,
            statResetRequiredResets: $profile === 'stat-reset' ? (int) $validated['statResetRequiredResets'] : $current->statResetRequiredResets,
            statResetZenCost: $profile === 'stat-reset' ? (int) $validated['statResetZenCost'] : $current->statResetZenCost,
            pkClearEnabled: $profile === 'pk-clear' ? (bool) $validated['pkClearEnabled'] : $current->pkClearEnabled,
            pkClearRequiredLevel: $profile === 'pk-clear' ? (int) $validated['pkClearRequiredLevel'] : $current->pkClearRequiredLevel,
            pkClearBaseZenCost: $profile === 'pk-clear' ? (int) $validated['pkClearBaseZenCost'] : $current->pkClearBaseZenCost,
            pkClearPriceMode: $profile === 'pk-clear' ? PkClearPriceMode::from((string) $validated['pkClearPriceMode']) : $current->pkClearPriceMode,
            moveToTownEnabled: $profile === 'move-to-town' ? (bool) $validated['moveToTownEnabled'] : $current->moveToTownEnabled,
            moveToTownMap: $profile === 'move-to-town' ? (int) $validated['moveToTownMap'] : $current->moveToTownMap,
            moveToTownX: $profile === 'move-to-town' ? (int) $validated['moveToTownX'] : $current->moveToTownX,
            moveToTownY: $profile === 'move-to-town' ? (int) $validated['moveToTownY'] : $current->moveToTownY,
            moveToTownZenCost: $profile === 'move-to-town' ? (int) $validated['moveToTownZenCost'] : $current->moveToTownZenCost,
        );
    }

    /**
     * Returns the route-selected profile slug used by validation and merging.
     */
    private function profile(): string
    {
        return (string) $this->route('profile');
    }

    /**
     * Returns one optional validated integer without converting absence to zero.
     *
     * @param  array<string, mixed>  $validated
     */
    private function optionalInteger(array $validated, string $key): ?int
    {
        return isset($validated[$key]) ? (int) $validated[$key] : null;
    }
}
