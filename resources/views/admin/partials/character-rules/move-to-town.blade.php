<div class="flex items-start justify-between gap-4 border-b border-white/8 pb-6">
    <div><p class="kicker">{{ __('ui.admin.move_to_town_profile') }}</p><p class="mt-2 text-sm text-steel">{{ __('ui.admin.move_to_town_profile_help') }}</p></div>
    <label class="toggle-control"><input type="hidden" name="moveToTownEnabled" value="0"><input type="checkbox" name="moveToTownEnabled" value="1" @checked(old('moveToTownEnabled', $settings['moveToTownEnabled']))><span aria-hidden="true"></span><span>{{ __('ui.admin.move_to_town_enabled') }}</span></label>
</div>
<div class="mt-7 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
    @foreach (['moveToTownMap' => ['move_to_town_map', 0, 255], 'moveToTownX' => ['move_to_town_x', 0, 255], 'moveToTownY' => ['move_to_town_y', 0, 255]] as $field => [$label, $minimum, $maximum])
        <label class="field-group"><span>{{ __('ui.admin.'.$label) }}</span><input type="number" name="{{ $field }}" value="{{ old($field, $settings[$field]) }}" min="{{ $minimum }}" max="{{ $maximum }}" required></label>
    @endforeach
    <label class="field-group"><span>{{ __('ui.admin.move_to_town_zen_cost') }}</span><input type="number" name="moveToTownZenCost" value="{{ old('moveToTownZenCost', $settings['moveToTownZenCost']) }}" min="0" max="2000000000" step="1000" required><small>{{ __('ui.admin.move_to_town_zen_cost_help') }}</small></label>
</div>
