<?php

namespace App\Http\Requests\Admin;

use App\Models\Administrator;
use App\Shop\EconomySettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates administrator-controlled paid-service pricing and switches.
 */
final class UpdateEconomySettingRequest extends FormRequest
{
    /**
     * Allows authenticated administrators through the request boundary.
     */
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Administrator;
    }

    /**
     * Returns bounded integer prices and explicit boolean feature switches.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'itemGeneratorMarkupPercentage' => ['required', 'integer', 'min:0', 'max:10000'],
            'characterRenameEnabled' => ['required', 'boolean'],
            'characterRenamePriceCredits' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
            'characterClassChangeEnabled' => ['required', 'boolean'],
            'characterClassChangePriceCredits' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
            'characterResetPurchaseEnabled' => ['required', 'boolean'],
            'characterResetPriceCredits' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
            'characterLevelUpPointsPurchaseEnabled' => ['required', 'boolean'],
            'characterLevelUpPointsPackageSize' => ['required', 'integer', 'min:1', 'max:1000000'],
            'characterLevelUpPointsPriceCredits' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
            'itemExchangeEnabled' => ['required', 'boolean'],
            'onlineTimeRewardsEnabled' => ['required', 'boolean'],
            'onlineMinutesPerUnit' => ['required', 'integer', 'min:1', 'max:1440'],
            'onlineCreditsPerUnit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'onlineDailyCreditLimit' => ['required', 'integer', 'min:1', 'max:1000000000', 'gte:onlineCreditsPerUnit'],
        ];
    }

    /**
     * Converts validated form values to the typed economy policy.
     */
    public function settings(): EconomySettings
    {
        $validated = $this->validated();

        return new EconomySettings(
            itemGeneratorMarkupPercentage: (int) $validated['itemGeneratorMarkupPercentage'],
            characterRenameEnabled: (bool) $validated['characterRenameEnabled'],
            characterRenamePriceCredits: (int) $validated['characterRenamePriceCredits'],
            characterClassChangeEnabled: (bool) $validated['characterClassChangeEnabled'],
            characterClassChangePriceCredits: (int) $validated['characterClassChangePriceCredits'],
            characterResetPurchaseEnabled: (bool) $validated['characterResetPurchaseEnabled'],
            characterResetPriceCredits: (int) $validated['characterResetPriceCredits'],
            characterLevelUpPointsPurchaseEnabled: (bool) $validated['characterLevelUpPointsPurchaseEnabled'],
            characterLevelUpPointsPackageSize: (int) $validated['characterLevelUpPointsPackageSize'],
            characterLevelUpPointsPriceCredits: (int) $validated['characterLevelUpPointsPriceCredits'],
            itemExchangeEnabled: (bool) $validated['itemExchangeEnabled'],
            onlineTimeRewardsEnabled: (bool) $validated['onlineTimeRewardsEnabled'],
            onlineMinutesPerUnit: (int) $validated['onlineMinutesPerUnit'],
            onlineCreditsPerUnit: (int) $validated['onlineCreditsPerUnit'],
            onlineDailyCreditLimit: (int) $validated['onlineDailyCreditLimit'],
        );
    }
}
