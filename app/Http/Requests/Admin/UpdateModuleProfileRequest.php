<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Modules\ModuleSettings;
use App\Modules\ServerModule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Authorizes and validates one complete module availability update.
 */
final class UpdateModuleProfileRequest extends FormRequest
{
    /**
     * Confirms the administrator may manage version-aware server settings.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageSettings);
    }

    /**
     * Returns allow-listed module switches and optimistic revision validation.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'revision' => ['required', 'integer', 'min:0'],
            'modules' => ['nullable', 'array'],
        ];

        foreach (ServerModule::cases() as $module) {
            $rules['modules.'.$module->value] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * Rejects module combinations whose public entry point is disabled.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $marketEnabled = filter_var(
                    $this->input('modules.'.ServerModule::Market->value, false),
                    FILTER_VALIDATE_BOOL,
                );
                $auctionEnabled = filter_var(
                    $this->input('modules.'.ServerModule::Auction->value, false),
                    FILTER_VALIDATE_BOOL,
                );
                $webStorageEnabled = filter_var(
                    $this->input('modules.'.ServerModule::WebStorage->value, false),
                    FILTER_VALIDATE_BOOL,
                );

                if ($marketEnabled && ! $webStorageEnabled) {
                    $validator->errors()->add(
                        'modules.'.ServerModule::Market->value,
                        __('ui.admin.modules_market_requires_web_storage'),
                    );
                }

                if ($auctionEnabled && ! $marketEnabled) {
                    $validator->errors()->add(
                        'modules.'.ServerModule::Auction->value,
                        __('ui.admin.modules_auction_requires_market'),
                    );
                }
            },
        ];
    }

    /**
     * Converts checkbox input into a complete typed snapshot.
     */
    public function settings(): ModuleSettings
    {
        $submittedModules = $this->validated('modules', []);
        $states = [];

        foreach (ServerModule::cases() as $module) {
            $states[$module->value] = filter_var(
                $submittedModules[$module->value] ?? false,
                FILTER_VALIDATE_BOOL,
            );
        }

        return ModuleSettings::fromArray($states);
    }

    /**
     * Returns the submitted revision used to reject stale forms.
     */
    public function expectedRevision(): int
    {
        return (int) $this->validated('revision');
    }
}
