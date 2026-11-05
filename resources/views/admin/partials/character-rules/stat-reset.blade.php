<div class="flex items-start justify-between gap-4 border-b border-white/8 pb-6">
    <div><p class="kicker">{{ __('ui.admin.stat_reset_profile') }}</p><p class="mt-2 text-sm text-steel">{{ __('ui.admin.stat_reset_profile_help') }}</p></div>
    <label class="toggle-control"><input type="hidden" name="statResetEnabled" value="0"><input type="checkbox" name="statResetEnabled" value="1" @checked(old('statResetEnabled', $settings['statResetEnabled']))><span aria-hidden="true"></span><span>{{ __('ui.admin.stat_reset_enabled') }}</span></label>
</div>
<div class="mt-7 grid gap-6 md:grid-cols-3">
    @foreach ([
        'statResetRequiredLevel' => ['stat_reset_required_level', 'stat_reset_required_level_help', 1, 400, 1],
        'statResetRequiredResets' => ['stat_reset_required_resets', 'stat_reset_required_resets_help', 0, $settings['maximumResets'], 1],
        'statResetZenCost' => ['stat_reset_zen_cost', 'stat_reset_zen_cost_help', 0, 2000000000, 1000000],
    ] as $field => [$label, $help, $minimum, $maximum, $step])
        <label class="field-group"><span>{{ __('ui.admin.'.$label) }}</span><input type="number" name="{{ $field }}" value="{{ old($field, $settings[$field]) }}" min="{{ $minimum }}" max="{{ $maximum }}" step="{{ $step }}" required><small>{{ __('ui.admin.'.$help) }}</small></label>
    @endforeach
</div>
