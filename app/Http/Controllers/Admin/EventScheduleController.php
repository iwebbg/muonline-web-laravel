<?php

namespace App\Http\Controllers\Admin;

use App\EventSchedules\Actions\SaveEventScheduleEntry;
use App\Game\Events\EventProfileReader;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventScheduleEntryRequest;
use App\Http\Requests\Admin\UpdateEventScheduleEntryRequest;
use App\Models\Administrator;
use App\Models\EventScheduleEntry;
use App\Models\EventScheduleEntryRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Manages bilingual event schedule overrides for the active server profile.
 */
final class EventScheduleController extends Controller
{
    /**
     * Displays effective definitions, editable overrides and revision history.
     */
    public function index(EventProfileReader $eventProfileReader): View
    {
        $profileKey = (string) config('game.event_profile');
        $gameVersion = (string) config('game.profile.version');
        $entries = EventScheduleEntry::query()
            ->where('profile_key', $profileKey)
            ->where('game_version', $gameVersion)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $entryIds = $entries->modelKeys();
        $recentRevisions = $entryIds === []
            ? collect()
            : EventScheduleEntryRevision::query()
                ->whereIn('event_schedule_entry_id', $entryIds)
                ->latest('id')
                ->limit(20)
                ->get();

        return view('admin.event-schedules', [
            'gameVersion' => $gameVersion,
            'effectiveDefinitions' => $eventProfileReader->definitions(),
            'entries' => $entries,
            'recentRevisions' => $recentRevisions,
        ]);
    }

    /**
     * Creates one new custom event or an override for a config-defined key.
     */
    public function store(
        StoreEventScheduleEntryRequest $request,
        SaveEventScheduleEntry $saveEventScheduleEntry,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $saveEventScheduleEntry->execute(
            attributes: $request->entryData(),
            administrator: $administrator,
        );

        return back()->with('status', __('ui.admin.event_schedule_created'));
    }

    /**
     * Updates one override only when it belongs to the active profile and version.
     */
    public function update(
        UpdateEventScheduleEntryRequest $request,
        EventScheduleEntry $eventScheduleEntry,
        SaveEventScheduleEntry $saveEventScheduleEntry,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);
        $this->abortUnlessActiveProfile($eventScheduleEntry);

        $saveEventScheduleEntry->execute(
            attributes: $request->entryData(),
            administrator: $administrator,
            eventScheduleEntry: $eventScheduleEntry,
        );

        return back()->with('status', __('ui.admin.event_schedule_updated'));
    }

    /**
     * Hides event records belonging to another configured realm profile.
     */
    private function abortUnlessActiveProfile(EventScheduleEntry $entry): void
    {
        abort_unless(
            $entry->profile_key === (string) config('game.event_profile')
            && $entry->game_version === (string) config('game.profile.version'),
            404,
        );
    }
}
