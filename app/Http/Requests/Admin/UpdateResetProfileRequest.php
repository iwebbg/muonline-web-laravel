<?php

namespace App\Http\Requests\Admin;

use App\GrandReset\GrandResetPointsMode;
use App\Models\Administrator;
use App\PkClear\PkClearPriceMode;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and normalizes one administrator reset policy update.
 */
final class UpdateResetProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Administrator;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'requiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
            'maximumResets' => ['required', 'integer', 'min:1', 'max:10000'],
            'maximumStat' => ['required', 'integer', 'min:1', 'max:32767'],
            'grandResetRequiredResets' => ['required', 'integer', 'min:1', 'max:10000', 'lte:maximumResets'],
            'baseZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'multiplyCostByReset' => ['required', 'boolean'],
            'clearStats' => ['required', 'boolean'],
            'pointsMode' => ['required', Rule::enum(PointsMode::class)],
            'pointsPerReset' => ['required', 'integer', 'min:0', 'max:32767'],
            'inventoryPolicy' => ['required', Rule::enum(InventoryPolicy::class)],
            'grandResetEnabled' => ['required', 'boolean'],
            'grandResetRequiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
            'maximumGrandResets' => ['required', 'integer', 'min:1', 'max:10000'],
            'grandResetBaseZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'grandResetMultiplyCost' => ['required', 'boolean'],
            'grandResetClearStats' => ['required', 'boolean'],
            'grandResetPointsMode' => ['required', Rule::enum(GrandResetPointsMode::class)],
            'grandResetPoints' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'grandResetInventoryPolicy' => ['required', Rule::enum(InventoryPolicy::class)],
            'grandResetConsumeRequiredResets' => ['required', 'boolean'],
            'statResetEnabled' => ['required', 'boolean'],
            'statResetRequiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
            'statResetRequiredResets' => ['required', 'integer', 'min:0', 'max:10000', 'lte:maximumResets'],
            'statResetZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'pkClearEnabled' => ['required', 'boolean'],
            'pkClearRequiredLevel' => ['required', 'integer', 'min:1', 'max:400'],
            'pkClearBaseZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'pkClearPriceMode' => ['required', Rule::enum(PkClearPriceMode::class)],
            'moveToTownEnabled' => ['required', 'boolean'],
            'moveToTownMap' => ['required', 'integer', 'min:0', 'max:255'],
            'moveToTownX' => ['required', 'integer', 'min:0', 'max:255'],
            'moveToTownY' => ['required', 'integer', 'min:0', 'max:255'],
            'moveToTownZenCost' => ['required', 'integer', 'min:0', 'max:2000000000'],
        ];
    }

    /**
     * Converts validated input to the typed domain policy consumed by the action.
     */
    public function settings(): ResetProfileSettings
    {
        $validated = $this->validated();

        return new ResetProfileSettings(
            enabled: (bool) $validated['enabled'],
            requiredLevel: (int) $validated['requiredLevel'],
            maximumResets: (int) $validated['maximumResets'],
            maximumStat: (int) $validated['maximumStat'],
            grandResetRequiredResets: (int) $validated['grandResetRequiredResets'],
            baseZenCost: (int) $validated['baseZenCost'],
            multiplyCostByReset: (bool) $validated['multiplyCostByReset'],
            clearStats: (bool) $validated['clearStats'],
            pointsMode: PointsMode::from((string) $validated['pointsMode']),
            pointsPerReset: (int) $validated['pointsPerReset'],
            inventoryPolicy: InventoryPolicy::from((string) $validated['inventoryPolicy']),
            grandResetEnabled: (bool) $validated['grandResetEnabled'],
            grandResetRequiredLevel: (int) $validated['grandResetRequiredLevel'],
            maximumGrandResets: (int) $validated['maximumGrandResets'],
            grandResetBaseZenCost: (int) $validated['grandResetBaseZenCost'],
            grandResetMultiplyCost: (bool) $validated['grandResetMultiplyCost'],
            grandResetClearStats: (bool) $validated['grandResetClearStats'],
            grandResetPointsMode: GrandResetPointsMode::from((string) $validated['grandResetPointsMode']),
            grandResetPoints: (int) $validated['grandResetPoints'],
            grandResetInventoryPolicy: InventoryPolicy::from((string) $validated['grandResetInventoryPolicy']),
            grandResetConsumeRequiredResets: (bool) $validated['grandResetConsumeRequiredResets'],
            statResetEnabled: (bool) $validated['statResetEnabled'],
            statResetRequiredLevel: (int) $validated['statResetRequiredLevel'],
            statResetRequiredResets: (int) $validated['statResetRequiredResets'],
            statResetZenCost: (int) $validated['statResetZenCost'],
            pkClearEnabled: (bool) $validated['pkClearEnabled'],
            pkClearRequiredLevel: (int) $validated['pkClearRequiredLevel'],
            pkClearBaseZenCost: (int) $validated['pkClearBaseZenCost'],
            pkClearPriceMode: PkClearPriceMode::from((string) $validated['pkClearPriceMode']),
            moveToTownEnabled: (bool) $validated['moveToTownEnabled'],
            moveToTownMap: (int) $validated['moveToTownMap'],
            moveToTownX: (int) $validated['moveToTownX'],
            moveToTownY: (int) $validated['moveToTownY'],
            moveToTownZenCost: (int) $validated['moveToTownZenCost'],
        );
    }
}
