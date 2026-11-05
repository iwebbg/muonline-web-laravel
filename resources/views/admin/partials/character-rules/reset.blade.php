<div class="flex items-start justify-between gap-4 border-b border-white/8 pb-6">
    <div>
        <p class="kicker">{{ __('ui.admin.reset_profile') }}</p>
        <p class="mt-2 text-sm text-steel">{{ __('ui.admin.version_aware') }}</p>
    </div>
    <label class="toggle-control">
        <input type="hidden" name="enabled" value="0">
        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
        <span aria-hidden="true"></span><span>{{ __('ui.admin.enabled') }}</span>
    </label>
</div>

<div class="mt-7 grid gap-6 md:grid-cols-2">
    @foreach ([
        'requiredLevel' => ['required_level', 'required_level_help', 1, 400, 1],
        'maximumResets' => ['maximum_resets', 'maximum_resets_help', 1, 10000, 1],
        'maximumStat' => ['maximum_stat', 'maximum_stat_help', 1, 32767, 1],
        'baseZenCost' => ['base_zen_cost', 'base_zen_cost_help', 0, 2000000000, 1000000],
        'pointsPerReset' => ['points_per_reset', 'points_per_reset_help', 0, 32767, 1],
    ] as $field => [$label, $help, $minimum, $maximum, $step])
        <label class="field-group">
            <span>{{ __('ui.admin.'.$label) }}</span>
            <input type="number" name="{{ $field }}" value="{{ old($field, $settings[$field]) }}" min="{{ $minimum }}" max="{{ $maximum }}" step="{{ $step }}" required>
            <small>{{ __('ui.admin.'.$help) }}</small>
        </label>
    @endforeach
    <label class="field-group">
        <span>{{ __('ui.admin.points_calculation') }}</span>
        <select name="pointsMode">
            <option value="cumulative" @selected(old('pointsMode', $settings['pointsMode']) === 'cumulative')>{{ __('ui.admin.points_cumulative') }}</option>
            <option value="fixed" @selected(old('pointsMode', $settings['pointsMode']) === 'fixed')>{{ __('ui.admin.points_fixed') }}</option>
        </select>
    </label>
    <label class="field-group md:col-span-2">
        <span>{{ __('ui.admin.inventory_policy') }}</span>
        <select name="inventoryPolicy">
            <option value="keep_all" @selected(old('inventoryPolicy', $settings['inventoryPolicy']) === 'keep_all')>{{ __('ui.admin.keep_all') }}</option>
            <option value="clear_bag" @selected(old('inventoryPolicy', $settings['inventoryPolicy']) === 'clear_bag')>{{ __('ui.admin.clear_bag') }}</option>
            <option value="clear_all" @selected(old('inventoryPolicy', $settings['inventoryPolicy']) === 'clear_all')>{{ __('ui.admin.clear_all') }}</option>
        </select>
        <small>{{ __('ui.admin.destructive_confirmation') }}</small>
    </label>
</div>

<div class="mt-7 grid gap-3 sm:grid-cols-2">
    @foreach (['multiplyCostByReset' => ['scale_cost', 'scale_cost_help'], 'clearStats' => ['clear_stats', 'clear_stats_help']] as $field => [$label, $help])
        <label class="check-card">
            <input type="hidden" name="{{ $field }}" value="0">
            <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings[$field]))>
            <span><strong>{{ __('ui.admin.'.$label) }}</strong><small>{{ __('ui.admin.'.$help) }}</small></span>
        </label>
    @endforeach
</div>
