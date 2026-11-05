<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one bounded item-grant account search.
 */
final class SearchItemGrantAccountRequest extends FormRequest
{
    /**
     * Allows only administrators with the granular item-grant capability.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::GrantItems);
    }

    /**
     * Restricts searches to meaningful legacy account identifier fragments.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'required',
                'string',
                'between:2,10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
        ];
    }

    /**
     * Returns the normalized account identifier fragment.
     */
    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }
}
