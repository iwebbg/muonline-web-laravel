<div class="grid gap-5 md:grid-cols-2">
    <label class="field-group">
        <span>{{ __('ui.admin.event_key') }}</span>
        <input type="text" name="event_key" value="{{ old('event_key', $entry?->event_key) }}" maxlength="80" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.event_sort_order') }}</span>
        <input type="number" name="sort_order" value="{{ old('sort_order', $entry?->sort_order ?? 0) }}" min="0" max="10000" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.event_name_en') }}</span>
        <input type="text" name="name_en" value="{{ old('name_en', $entry?->name_en) }}" maxlength="120" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.event_name_bg') }}</span>
        <input type="text" name="name_bg" value="{{ old('name_bg', $entry?->name_bg) }}" maxlength="120">
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.event_location_en') }}</span>
        <input type="text" name="location_en" value="{{ old('location_en', $entry?->location_en) }}" maxlength="120" required>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.event_location_bg') }}</span>
        <input type="text" name="location_bg" value="{{ old('location_bg', $entry?->location_bg) }}" maxlength="120">
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.event_accent') }}</span>
        <select name="accent" required>
            @foreach (['crimson', 'violet', 'amber'] as $accent)
                <option value="{{ $accent }}" @selected(old('accent', $entry?->accent ?? 'crimson') === $accent)>{{ __('ui.admin.event_accents.'.$accent) }}</option>
            @endforeach
        </select>
    </label>
    <label class="field-group">
        <span>{{ __('ui.admin.event_duration') }}</span>
        <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $entry?->duration_minutes ?? 0) }}" min="0" max="1440" required>
    </label>
</div>

<div class="grid gap-5 md:grid-cols-2">
    <label class="field-group">
        <span>{{ __('ui.admin.event_schedule_mode') }}</span>
        <select name="schedule_mode" x-model="scheduleMode" required>
            <option value="times">{{ __('ui.admin.event_mode_times') }}</option>
            <option value="interval">{{ __('ui.admin.event_mode_interval') }}</option>
        </select>
    </label>
    <label class="field-group" x-show="scheduleMode === 'times'">
        <span>{{ __('ui.admin.event_times') }}</span>
        <input type="text" name="times" value="{{ old('times', $entry === null ? '' : implode(', ', $entry->times)) }}" placeholder="10:00, 15:30">
    </label>
    <label class="field-group" x-show="scheduleMode === 'interval'" x-cloak>
        <span>{{ __('ui.admin.event_interval') }}</span>
        <input type="number" name="interval_minutes" value="{{ old('interval_minutes', $entry?->interval_minutes) }}" min="1" max="1440">
    </label>
    <label class="field-group" x-show="scheduleMode === 'interval'" x-cloak>
        <span>{{ __('ui.admin.event_anchor') }}</span>
        <input type="time" name="interval_anchor" value="{{ old('interval_anchor', $entry?->interval_anchor ?? '00:00') }}">
    </label>
</div>

<fieldset>
    <legend class="text-xs font-semibold uppercase tracking-[0.14em] text-steel">{{ __('ui.admin.event_days') }}</legend>
    <div class="mt-3 flex flex-wrap gap-3">
        @foreach (range(1, 7) as $day)
            <label class="flex items-center gap-2 rounded-xl border border-white/8 bg-black/20 px-3 py-2 text-sm text-silver">
                <input type="checkbox" name="days_of_week[]" value="{{ $day }}" @checked(in_array($day, old('days_of_week', $entry?->days_of_week ?? []), true))>
                {{ __('ui.home.weekdays.'.$day) }}
            </label>
        @endforeach
    </div>
    <p class="mt-2 text-xs text-steel">{{ __('ui.admin.event_days_help') }}</p>
</fieldset>

<label class="field-group">
    <span>{{ __('ui.admin.event_source') }}</span>
    <textarea name="source" rows="2" maxlength="1000">{{ old('source', $entry?->source) }}</textarea>
</label>

<label class="flex items-center gap-3 text-sm text-silver">
    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $entry?->enabled ?? true))>
    {{ __('ui.admin.event_enabled') }}
</label>
