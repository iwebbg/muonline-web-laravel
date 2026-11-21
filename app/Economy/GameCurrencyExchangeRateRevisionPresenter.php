<?php

namespace App\Economy;

use App\Models\GameCurrencyExchangeRateRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Presents immutable exchange rate revisions through the shared administrator change dialog.
 */
final class GameCurrencyExchangeRateRevisionPresenter
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'credit_units' => 'credit_units',
        'coin_units' => 'coin_units',
        'minimum_units' => 'minimum_units',
        'maximum_units' => 'maximum_units',
        'enabled' => 'enabled',
    ];

    /**
     * Identifies each currency and presents only changed exchange policy fields.
     *
     * @param  Collection<int, GameCurrencyExchangeRateRevision>  $revisions
     * @return list<array{revision: int, context: string, createdAt: string, changes: list<array{label: string, before: string, after: string}>}>
     */
    public function present(Collection $revisions): array
    {
        $presentedRevisions = [];
        foreach ($revisions as $revision) {
            $currencyValue = (string) ($revision->snapshot['currency'] ?? $revision->before_snapshot['currency'] ?? '');
            $currency = NativeCurrency::tryFrom($currencyValue);
            $presentedRevisions[] = [
                'revision' => $revision->revision,
                'context' => $currency?->label() ?? $currencyValue,
                'createdAt' => $revision->created_at?->diffForHumans() ?? '',
                'changes' => $this->changesFor($revision),
            ];
        }

        return $presentedRevisions;
    }

    /**
     * Compares captured terms without including unchanged identity and revision metadata.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    private function changesFor(GameCurrencyExchangeRateRevision $revision): array
    {
        $beforeSnapshot = $revision->before_snapshot ?? [];
        $afterSnapshot = $revision->snapshot ?? [];
        $changes = [];
        foreach (self::FIELD_LABELS as $field => $labelKey) {
            $before = $beforeSnapshot[$field] ?? null;
            $after = $afterSnapshot[$field] ?? null;
            if ($before === $after) {
                continue;
            }
            $changes[] = [
                'label' => __('ui.native_services.'.$labelKey),
                'before' => $this->formatValue($before),
                'after' => $this->formatValue($after),
            ];
        }

        return $changes;
    }

    /**
     * Formats values using the same availability and number conventions as other revision panels.
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return __('ui.admin.revision_value_not_set');
        }
        if (is_bool($value)) {
            return $value ? __('ui.admin.revision_value_enabled') : __('ui.admin.revision_value_disabled');
        }
        if (is_int($value)) {
            return number_format($value);
        }

        return (string) $value;
    }
}
