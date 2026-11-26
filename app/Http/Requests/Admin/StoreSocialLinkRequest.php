<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Website\SocialPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates an administrator-managed public social destination. */
final class StoreSocialLinkRequest extends FormRequest
{
    /** Restricts community-link changes to website settings managers. */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageSettings);
    }

    /**
     * Allows known networks, optional channel labels and HTTP destinations only.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SocialPlatform::class)],
            'label' => ['nullable', 'string', 'max:80'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
