<?php

namespace App\Website;

use App\Models\WebsiteSetting;

/**
 * Carries the resolved website branding and search metadata configuration.
 */
final readonly class WebsiteSettings
{
    /**
     * Creates one typed website settings snapshot.
     */
    public function __construct(
        public string $brandTitle,
        public string $brandSubtitle,
        public ?string $logoPath,
        public ?string $homeTitleEn,
        public ?string $homeTitleBg,
        public string $pageTitleSuffix,
        public ?string $metaDescriptionEn,
        public ?string $metaDescriptionBg,
        public ?string $metaKeywordsEn,
        public ?string $metaKeywordsBg,
        public ?string $ogImagePath,
        public ?string $copyrightHolder,
        public string $themeSlug,
        public int $revision,
    ) {}

    /**
     * Returns the reviewed values used when no administrator revision exists.
     */
    public static function defaults(?string $serverVersion = null): self
    {
        return new self(
            brandTitle: 'MUONLINE',
            brandSubtitle: $serverVersion ?? (string) config('game.profile.version'),
            logoPath: null,
            homeTitleEn: null,
            homeTitleBg: null,
            pageTitleSuffix: (string) config('app.name', 'MuOnline Web'),
            metaDescriptionEn: null,
            metaDescriptionBg: null,
            metaKeywordsEn: null,
            metaKeywordsBg: null,
            ogImagePath: null,
            copyrightHolder: null,
            themeSlug: (string) config('themes.default', 'muonline'),
            revision: 0,
        );
    }

    /**
     * Resolves a persisted record while retaining safe fallbacks for blank fields.
     */
    public static function fromModel(WebsiteSetting $setting, ?string $serverVersion = null): self
    {
        $defaults = self::defaults($serverVersion);

        return new self(
            brandTitle: self::fallback($setting->brand_title, $defaults->brandTitle),
            brandSubtitle: self::fallback($setting->brand_subtitle, $defaults->brandSubtitle),
            logoPath: self::nullable($setting->logo_path),
            homeTitleEn: self::nullable($setting->home_title_en),
            homeTitleBg: self::nullable($setting->home_title_bg),
            pageTitleSuffix: self::fallback($setting->page_title_suffix, $defaults->pageTitleSuffix),
            metaDescriptionEn: self::nullable($setting->meta_description_en),
            metaDescriptionBg: self::nullable($setting->meta_description_bg),
            metaKeywordsEn: self::nullable($setting->meta_keywords_en),
            metaKeywordsBg: self::nullable($setting->meta_keywords_bg),
            ogImagePath: self::nullable($setting->og_image_path),
            copyrightHolder: self::nullable($setting->copyright_holder),
            themeSlug: self::fallback($setting->theme_slug, $defaults->themeSlug),
            revision: $setting->revision,
        );
    }

    /**
     * Returns the configured home title for the active locale when present.
     */
    public function localizedHomeTitle(string $locale): ?string
    {
        return $locale === 'bg'
            ? ($this->homeTitleBg ?? $this->homeTitleEn)
            : $this->homeTitleEn;
    }

    /**
     * Returns the configured description with English as the locale fallback.
     */
    public function localizedDescription(string $locale): ?string
    {
        return $locale === 'bg'
            ? ($this->metaDescriptionBg ?? $this->metaDescriptionEn)
            : $this->metaDescriptionEn;
    }

    /**
     * Returns the configured keywords with English as the locale fallback.
     */
    public function localizedKeywords(string $locale): ?string
    {
        return $locale === 'bg'
            ? ($this->metaKeywordsBg ?? $this->metaKeywordsEn)
            : $this->metaKeywordsEn;
    }

    /**
     * Builds the final document title from a page label and the managed suffix.
     */
    public function documentTitle(string $pageTitle, bool $isHome, string $locale): string
    {
        if ($isHome) {
            return $this->localizedHomeTitle($locale) ?? $pageTitle;
        }

        $legacySuffixes = array_unique([
            self::defaults()->pageTitleSuffix,
            'MuOnline Web',
        ]);
        $escapedSuffixes = array_map(
            fn (string $suffix): string => preg_quote($suffix, '/'),
            $legacySuffixes,
        );
        $normalizedTitle = preg_replace(
            '/\s*[·-]\s*(?:'.implode('|', $escapedSuffixes).')\z/u',
            '',
            trim($pageTitle),
        );
        $normalizedTitle = is_string($normalizedTitle) && $normalizedTitle !== ''
            ? $normalizedTitle
            : trim($pageTitle);

        if ($normalizedTitle === $this->pageTitleSuffix) {
            return $normalizedTitle;
        }

        return $normalizedTitle.' - '.$this->pageTitleSuffix;
    }

    /**
     * Returns the explicit copyright holder or the managed title suffix fallback.
     */
    public function resolvedCopyrightHolder(): string
    {
        return $this->copyrightHolder ?? $this->pageTitleSuffix;
    }

    /**
     * Converts the typed values into application-owned persistence columns.
     *
     * @return array<string, string|null>
     */
    public function toPersistenceArray(): array
    {
        return [
            'brand_title' => $this->brandTitle,
            'brand_subtitle' => $this->brandSubtitle,
            'logo_path' => $this->logoPath,
            'home_title_en' => $this->homeTitleEn,
            'home_title_bg' => $this->homeTitleBg,
            'page_title_suffix' => $this->pageTitleSuffix,
            'meta_description_en' => $this->metaDescriptionEn,
            'meta_description_bg' => $this->metaDescriptionBg,
            'meta_keywords_en' => $this->metaKeywordsEn,
            'meta_keywords_bg' => $this->metaKeywordsBg,
            'og_image_path' => $this->ogImagePath,
            'copyright_holder' => $this->copyrightHolder,
            'theme_slug' => $this->themeSlug,
        ];
    }

    /**
     * Converts a potentially blank database value to a meaningful fallback.
     */
    private static function fallback(mixed $value, string $fallback): string
    {
        return self::nullable($value) ?? $fallback;
    }

    /**
     * Converts a potentially blank database value to a normalized string.
     */
    private static function nullable(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
