<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Content\NewsPostType;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and normalizes administrator content-list filters.
 */
final class FilterNewsPostsRequest extends FormRequest
{
    /**
     * Allows administrators with content-management permission to filter articles.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageContent);
    }

    /**
     * Returns bounded and allow-listed content filter rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'type' => [
                'nullable',
                Rule::enum(NewsPostType::class),
            ],
        ];
    }

    /**
     * Returns the trimmed bilingual title or slug search phrase.
     */
    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    /**
     * Returns the selected content collection or null for all collections.
     */
    public function type(): ?NewsPostType
    {
        return NewsPostType::tryFrom($this->string('type')->toString());
    }
}
