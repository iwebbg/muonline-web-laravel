<?php

namespace App\EventSchedules;

use App\Models\EventScheduleEntryRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Converts event schedule revisions into administrator-facing changes.
 */
final class EventScheduleEntryRevisionPresenter
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'event_key' => 'event_key',
        'name_en' => 'event_name_en',
        'name_bg' => 'event_name_bg',
        'location_en' => 'event_location_en',
        'location_bg' => 'event_location_bg',
        'accent' => 'event_accent',
        'enabled' => 'event_enabled',
        'duration_minutes' => 'event_duration',
        'schedule_mode' => 'event_schedule_mode',
        'times' => 'event_times',
        'days_of_week' => 'event_days',
        'interval_minutes' => 'event_interval',
        'interval_anchor' => 'event_anchor',
        'source' => 'event_source',
        'sort_order' => 'event_sort_order',
    ];

    /**
     * Presents recent revisions with event context and changed schedule values.
     *
     * @param  Collection<int, EventScheduleEntryRevision>  $revisions
     * @return list<array{revision: int, context: string, createdAt: string, changes: list<array{label: string, before: string, after: string}>}>
     */
    public function present(Collection $revisions): array
    {
        $presentedRevisions = [];

        foreach ($revisions as $revision) {
            $presentedRevisions[] = [
                'revision' => $revision->revision,
                'context' => $this->eventContext($revision),
                'createdAt' => $revision->created_at?->diffForHumans() ?? '',
                'changes' => $this->changesFor($revision),
            ];
        }

        return $presentedRevisions;
    }

    /**
     * Extracts and labels changed event values from one immutable snapshot pair.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    private function changesFor(EventScheduleEntryRevision $revision): array
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
     * Identifies the event and action represented by one revision card.
     */
    private function eventContext(EventScheduleEntryRevision $revision): string
    {
        $beforeSnapshot = $revision->before_snapshot ?? [];
        $afterSnapshot = $revision->after_snapshot ?? [];
        $eventKey = $afterSnapshot['event_key'] ?? $beforeSnapshot['event_key'] ?? '#'.$revision->event_schedule_entry_id;
        $action = match ($revision->action) {
            'created' => __('ui.admin.revision_action_created'),
            'updated' => __('ui.admin.revision_action_updated'),
            default => ucfirst((string) $revision->action),
        };

        return (string) $eventKey.' · '.$action;
    }

    /**
     * Formats stored event values without exposing internal option identifiers.
     */
    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return __('ui.admin.revision_value_not_set');
        }

        if (is_bool($value)) {
            return $value
                ? __('ui.admin.revision_value_enabled')
                : __('ui.admin.revision_value_disabled');
        }

        if ($field === 'schedule_mode' && is_string($value)) {
            return match ($value) {
                'times' => __('ui.admin.event_mode_times'),
                'interval' => __('ui.admin.event_mode_interval'),
                default => $value,
            };
        }

        if ($field === 'days_of_week' && is_array($value)) {
            return $this->formatWeekdays($value);
        }

        if (is_array($value)) {
            return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        if (is_int($value)) {
            return number_format($value);
        }

        return (string) $value;
    }

    /**
     * Converts stored weekday numbers into localized weekday names.
     *
     * @param  array<int, mixed>  $weekdays
     */
    private function formatWeekdays(array $weekdays): string
    {
        $weekdayLabels = __('ui.home.weekdays');
        $formattedWeekdays = [];

        foreach ($weekdays as $weekday) {
            $weekdayNumber = (int) $weekday;
            $formattedWeekdays[] = is_array($weekdayLabels)
                ? (string) ($weekdayLabels[$weekdayNumber] ?? $weekdayNumber)
                : (string) $weekdayNumber;
        }

        return implode(', ', $formattedWeekdays);
    }
}
