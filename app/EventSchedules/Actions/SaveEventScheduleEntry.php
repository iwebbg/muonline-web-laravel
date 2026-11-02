<?php

namespace App\EventSchedules\Actions;

use App\Models\Administrator;
use App\Models\EventScheduleEntry;
use App\Models\EventScheduleEntryRevision;
use Illuminate\Database\DatabaseManager;

/**
 * Creates or updates one event override with an immutable audit revision.
 */
final readonly class SaveEventScheduleEntry
{
    /**
     * Creates the action with Laravel's application database manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Saves one complete event snapshot in a transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        array $attributes,
        Administrator $administrator,
        ?EventScheduleEntry $eventScheduleEntry = null,
    ): EventScheduleEntry {
        return $this->database->transaction(function () use (
            $attributes,
            $administrator,
            $eventScheduleEntry,
        ): EventScheduleEntry {
            $lockedEntry = $eventScheduleEntry === null
                ? null
                : EventScheduleEntry::query()
                    ->lockForUpdate()
                    ->findOrFail($eventScheduleEntry->getKey());
            $beforeSnapshot = $lockedEntry?->only(array_keys($attributes));
            $revision = ($lockedEntry?->revision ?? 0) + 1;
            $persistedAttributes = [
                ...$attributes,
                'profile_key' => (string) config('game.event_profile'),
                'game_version' => (string) config('game.profile.version'),
                'revision' => $revision,
                'updated_by_administrator_id' => $administrator->getKey(),
            ];

            if ($lockedEntry === null) {
                $lockedEntry = EventScheduleEntry::query()->create($persistedAttributes);
            } else {
                $lockedEntry->update($persistedAttributes);
            }

            EventScheduleEntryRevision::query()->create([
                'event_schedule_entry_id' => $lockedEntry->getKey(),
                'revision' => $revision,
                'action' => $eventScheduleEntry === null ? 'created' : 'updated',
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $lockedEntry->fresh()->only(array_keys($attributes)),
                'changed_by_administrator_id' => $administrator->getKey(),
            ]);

            return $lockedEntry->refresh();
        });
    }
}
