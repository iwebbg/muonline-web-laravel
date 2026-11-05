<?php

namespace App\Website;

use App\Models\WebsiteSettingRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Converts website identity revisions into administrator-facing changes.
 */
final class WebsiteSettingRevisionPresenter
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'brand_title' => 'website_brand_title',
        'brand_subtitle' => 'website_brand_subtitle',
        'logo_path' => 'website_logo',
        'home_title_en' => 'website_home_title_en',
        'home_title_bg' => 'website_home_title_bg',
        'page_title_suffix' => 'website_title_suffix',
        'meta_description_en' => 'website_description_en',
        'meta_description_bg' => 'website_description_bg',
        'meta_keywords_en' => 'website_keywords_en',
        'meta_keywords_bg' => 'website_keywords_bg',
        'og_image_path' => 'website_og_image',
        'copyright_holder' => 'website_copyright_holder',
    ];

    /** @var list<string> */
    private const ASSET_FIELDS = [
        'logo_path',
        'og_image_path',
    ];

    /**
     * Presents recent revisions with their publisher and changed identity values.
     *
     * @param  Collection<int, WebsiteSettingRevision>  $revisions
     * @return list<array{revision: int, context: string, createdAt: string, changes: list<array{label: string, before: string, after: string}>}>
     */
    public function present(Collection $revisions): array
    {
        $presentedRevisions = [];

        foreach ($revisions as $revision) {
            $presentedRevisions[] = [
                'revision' => $revision->revision,
                'context' => $this->publisherContext($revision),
                'createdAt' => $revision->created_at?->diffForHumans() ?? '',
                'changes' => $this->changesFor($revision),
            ];
        }

        return $presentedRevisions;
    }

    /**
     * Extracts and labels changed website values from one immutable snapshot pair.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    private function changesFor(WebsiteSettingRevision $revision): array
    {
        $beforeSnapshot = $revision->before_snapshot ?? [];
        $afterSnapshot = $revision->after_snapshot ?? [];
        $changes = [];

        foreach (self::FIELD_LABELS as $field => $labelKey) {
            $beforeValue = $beforeSnapshot[$field] ?? null;
            $afterValue = $afterSnapshot[$field] ?? null;

            if ($beforeValue === $afterValue) {
                continue;
            }

            $changes[] = [
                'label' => __('ui.admin.'.$labelKey),
                'before' => $this->formatValue($field, $beforeValue),
                'after' => $this->formatValue($field, $afterValue),
            ];
        }

        return $changes;
    }

    /**
     * Identifies the administrator that published one website revision.
     */
    private function publisherContext(WebsiteSettingRevision $revision): string
    {
        $administrator = $revision->changedByAdministrator;

        if ($administrator === null) {
            return __('ui.admin.modules_system');
        }

        return $administrator->name.' · '.$administrator->email;
    }

    /**
     * Formats stored website values while hiding managed storage directories.
     */
    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('ui.admin.revision_value_not_set');
        }

        if (in_array($field, self::ASSET_FIELDS, true) && is_string($value)) {
            return basename($value);
        }

        return (string) $value;
    }
}
