<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Authorizes and validates creation of one vote destination and reward mode.
 */
class StoreVoteSiteRequest extends FormRequest
{
    /**
     * Confirms that the administrator may manage the server economy.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Returns the bounded destination, cooldown and reward validation rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('vote_sites', 'slug'),
            ],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bg' => ['nullable', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'cooldown_hours' => ['required', 'integer', 'between:1,168'],
            'reward_credits' => ['required', 'integer', 'between:0,1000000000'],
            'reward_zen' => ['required', 'integer', 'between:0,2000000000'],
            'auto_reward_on_click' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
        ];
    }

    /**
     * Requires at least one positive reward without coupling the two currencies.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['reward_credits', 'reward_zen'])) {
                return;
            }

            if ($this->integer('reward_credits') === 0 && $this->integer('reward_zen') === 0) {
                $validator->errors()->add('reward_credits', __('ui.votes.errors.invalid_reward'));
            }
        }];
    }

    /**
     * Returns only normalized attributes accepted by the vote-site model.
     *
     * @return array<string, mixed>
     */
    public function voteSiteData(): array
    {
        return [
            ...$this->safe()->except(['auto_reward_on_click', 'enabled']),
            'auto_reward_on_click' => $this->boolean('auto_reward_on_click'),
            'enabled' => $this->boolean('enabled'),
        ];
    }
}
