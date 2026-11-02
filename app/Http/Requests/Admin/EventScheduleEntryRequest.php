<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\EventScheduleEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Defines the shared authorization and validation boundary for event overrides.
 */
abstract class EventScheduleEntryRequest extends FormRequest
{
    /**
     * Confirms the administrator may manage server-profile settings.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageSettings);
    }

    /**
     * Returns bilingual event and mutually exclusive schedule constraints.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $routeEntry = $this->route('eventScheduleEntry');
        $uniqueKey = Rule::unique('event_schedule_entries', 'event_key')
            ->where('profile_key', (string) config('game.event_profile'))
            ->where('game_version', (string) config('game.profile.version'));

        if ($routeEntry instanceof EventScheduleEntry) {
            $uniqueKey->ignore($routeEntry);
        }

        return [
            'event_key' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $uniqueKey],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bg' => ['nullable', 'string', 'max:120'],
            'location_en' => ['required', 'string', 'max:120'],
            'location_bg' => ['nullable', 'string', 'max:120'],
            'accent' => ['required', Rule::in(['crimson', 'violet', 'amber'])],
            'enabled' => ['nullable', 'boolean'],
            'duration_minutes' => ['required', 'integer', 'between:0,1440'],
            'schedule_mode' => ['required', Rule::in(['times', 'interval'])],
            'times' => [
                'nullable',
                'required_if:schedule_mode,times',
                'string',
                'max:500',
                'regex:/\A\s*(?:[01]\d|2[0-3]):[0-5]\d(?:[\s,]+(?:[01]\d|2[0-3]):[0-5]\d)*\s*\z/',
            ],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7', 'distinct'],
            'interval_minutes' => ['nullable', 'required_if:schedule_mode,interval', 'integer', 'between:1,1440'],
            'interval_anchor' => ['nullable', 'required_if:schedule_mode,interval', 'date_format:H:i'],
            'source' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
        ];
    }

    /**
     * Returns normalized model attributes from the validated form.
     *
     * @return array<string, mixed>
     */
    public function entryData(): array
    {
        $validated = $this->validated();
        $intervalMode = $validated['schedule_mode'] === 'interval';
        $timesText = (string) ($validated['times'] ?? '');
        $times = $timesText === ''
            ? []
            : preg_split('/[\s,]+/', trim($timesText));
        $normalizedTimes = ! $intervalMode && is_array($times)
            ? array_values(array_unique($times))
            : [];
        $daysOfWeek = array_map('intval', $validated['days_of_week'] ?? []);

        sort($normalizedTimes, SORT_STRING);
        sort($daysOfWeek, SORT_NUMERIC);

        return [
            'event_key' => (string) $validated['event_key'],
            'name_en' => (string) $validated['name_en'],
            'name_bg' => $validated['name_bg'] ?? null,
            'location_en' => (string) $validated['location_en'],
            'location_bg' => $validated['location_bg'] ?? null,
            'accent' => (string) $validated['accent'],
            'enabled' => $this->boolean('enabled'),
            'duration_minutes' => (int) $validated['duration_minutes'],
            'schedule_mode' => (string) $validated['schedule_mode'],
            'times' => $normalizedTimes,
            'days_of_week' => $daysOfWeek,
            'interval_minutes' => $intervalMode && isset($validated['interval_minutes'])
                ? (int) $validated['interval_minutes']
                : null,
            'interval_anchor' => $intervalMode
                ? (string) $validated['interval_anchor']
                : '00:00',
            'source' => $validated['source'] ?? null,
            'sort_order' => (int) $validated['sort_order'],
        ];
    }
}
