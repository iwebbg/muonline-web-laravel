<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Content\DownloadCategory;
use App\Content\NewsPostStatus;
use App\Models\Administrator;
use App\Models\Download;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates an audited bilingual download update.
 */
final class UpdateDownloadRequest extends FormRequest
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
        $download = $this->route('download');

        return [
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('downloads', 'slug')->ignore(
                    $download instanceof Download ? $download->getKey() : null,
                ),
            ],
            'category' => ['required', Rule::enum(DownloadCategory::class)],
            'title_en' => ['required', 'string', 'max:160'],
            'title_bg' => ['nullable', 'string', 'max:160'],
            'description_en' => ['required', 'string', 'max:5000'],
            'description_bg' => ['nullable', 'string', 'max:5000'],
            'version' => ['nullable', 'string', 'max:80'],
            'file_size' => ['nullable', 'string', 'max:40'],
            'download_url' => ['required', 'string', 'max:2048', 'url:https'],
            'checksum_sha256' => [
                'nullable',
                'string',
                'regex:/\A[a-fA-F0-9]{64}\z/',
            ],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
            'status' => ['required', Rule::enum(NewsPostStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Returns only validated fields accepted by the download model.
     *
     * @return array<string, mixed>
     */
    public function contentData(): array
    {
        return $this->safe()->only([
            'slug',
            'category',
            'title_en',
            'title_bg',
            'description_en',
            'description_bg',
            'version',
            'file_size',
            'download_url',
            'checksum_sha256',
            'sort_order',
            'status',
            'published_at',
        ]);
    }
}
