<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Themes\ThemeManager;
use App\Website\WebsiteSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one website branding and search metadata revision.
 */
final class UpdateWebsiteSettingRequest extends FormRequest
{
    /**
     * Allows only administrator identities with settings permission to publish.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageSettings);
    }

    /**
     * Returns allow-listed text fields and content-verified image constraints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ThemeManager $themes): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            'brand_title' => ['nullable', 'string', 'max:80'],
            'brand_subtitle' => ['nullable', 'string', 'max:120'],
            'home_title_en' => ['nullable', 'string', 'max:160'],
            'home_title_bg' => ['nullable', 'string', 'max:160'],
            'page_title_suffix' => ['nullable', 'string', 'max:120'],
            'meta_description_en' => ['nullable', 'string', 'max:320'],
            'meta_description_bg' => ['nullable', 'string', 'max:320'],
            'meta_keywords_en' => ['nullable', 'string', 'max:500'],
            'meta_keywords_bg' => ['nullable', 'string', 'max:500'],
            'copyright_holder' => ['nullable', 'string', 'max:160'],
            'theme_slug' => ['required', 'string', Rule::in(array_keys($themes->available()))],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2400,max_height=1200'],
            'og_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=4000,max_height=4000'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_og_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Builds the typed settings snapshot with already resolved image paths.
     */
    public function settings(?string $logoPath, ?string $ogImagePath): WebsiteSettings
    {
        $defaults = WebsiteSettings::defaults();

        return new WebsiteSettings(
            brandTitle: $this->normalized('brand_title') ?? $defaults->brandTitle,
            brandSubtitle: $this->normalized('brand_subtitle') ?? $defaults->brandSubtitle,
            logoPath: $logoPath,
            homeTitleEn: $this->normalized('home_title_en'),
            homeTitleBg: $this->normalized('home_title_bg'),
            pageTitleSuffix: $this->normalized('page_title_suffix') ?? $defaults->pageTitleSuffix,
            metaDescriptionEn: $this->normalized('meta_description_en'),
            metaDescriptionBg: $this->normalized('meta_description_bg'),
            metaKeywordsEn: $this->normalized('meta_keywords_en'),
            metaKeywordsBg: $this->normalized('meta_keywords_bg'),
            ogImagePath: $ogImagePath,
            copyrightHolder: $this->normalized('copyright_holder'),
            themeSlug: $this->string('theme_slug')->toString(),
            revision: $this->integer('revision'),
        );
    }

    /**
     * Returns the submitted optimistic revision.
     */
    public function expectedRevision(): int
    {
        return $this->integer('revision');
    }

    /**
     * Trims one validated optional string and converts blank text to null.
     */
    private function normalized(string $key): ?string
    {
        $value = $this->validated($key);

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
