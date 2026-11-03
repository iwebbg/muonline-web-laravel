<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one public server-directory destination.
 */
final class StoreGameServerRequest extends FormRequest
{
    /**
     * Allows administrators with server-setting access to create destinations.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageSettings);
    }

    /**
     * Returns validated public metadata and safe redirect constraints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('game_servers', 'slug'),
            ],
            'name' => ['required', 'string', 'max:160'],
            'version' => ['required', 'string', 'max:80'],
            'experience' => ['required', 'string', 'max:40'],
            'drop' => ['required', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'between:1,100000'],
            'show_online_status' => ['required', 'boolean'],
            'show_online_users' => ['required', 'boolean'],
            'is_current' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'website_url' => [
                Rule::requiredIf(! $this->boolean('is_current')),
                'nullable',
                'string',
                'max:2048',
                'url:http,https',
            ],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
        ];
    }

    /**
     * Returns the normalized attributes accepted by the server model.
     *
     * @return array<string, mixed>
     */
    public function serverData(): array
    {
        $attributes = $this->safe()->only([
            'slug',
            'name',
            'version',
            'experience',
            'drop',
            'capacity',
            'show_online_status',
            'show_online_users',
            'is_current',
            'is_visible',
            'website_url',
            'sort_order',
        ]);
        $attributes['capacity'] = $this->integer('capacity');
        $attributes['show_online_status'] = $this->boolean('show_online_status');
        $attributes['show_online_users'] = $this->boolean('show_online_users');
        $attributes['is_current'] = $this->boolean('is_current');
        $attributes['is_visible'] = $this->boolean('is_visible');
        $attributes['sort_order'] = $this->integer('sort_order');

        if ($attributes['is_current']) {
            $attributes['website_url'] = null;
        }

        return $attributes;
    }
}
