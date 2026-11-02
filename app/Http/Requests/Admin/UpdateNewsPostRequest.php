<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Content\NewsPostStatus;
use App\Content\NewsPostType;
use App\Models\Administrator;
use App\Models\NewsPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates an audited bilingual news article update.
 */
final class UpdateNewsPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageContent);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $newsPost = $this->route('newsPost');

        return [
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('news_posts', 'slug')->ignore(
                    $newsPost instanceof NewsPost ? $newsPost->getKey() : null,
                ),
            ],
            'type' => ['required', Rule::enum(NewsPostType::class)],
            'title_en' => ['required', 'string', 'max:160'],
            'title_bg' => ['nullable', 'string', 'max:160'],
            'excerpt_en' => ['required', 'string', 'max:300'],
            'excerpt_bg' => ['nullable', 'string', 'max:300'],
            'body_en' => ['required', 'string', 'max:50000'],
            'body_bg' => ['nullable', 'string', 'max:50000'],
            'status' => ['required', Rule::enum(NewsPostStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Returns the validated article fields accepted by the application model.
     *
     * @return array<string, mixed>
     */
    public function contentData(): array
    {
        return $this->safe()->only([
            'slug',
            'type',
            'title_en',
            'title_bg',
            'excerpt_en',
            'excerpt_bg',
            'body_en',
            'body_bg',
            'status',
            'published_at',
        ]);
    }
}
