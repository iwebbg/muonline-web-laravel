<div class="flex items-start justify-between gap-4 border-b border-white/8 pb-6">
    <div><p class="kicker">{{ __('ui.admin.grand_reset_profile') }}</p><p class="mt-2 text-sm text-steel">{{ __('ui.admin.grand_reset_enabled_help') }}</p></div>
    <label class="toggle-control"><input type="hidden" name="grandResetEnabled" value="0"><input type="checkbox" name="grandResetEnabled" value="1" @checked(old('grandResetEnabled', $settings['grandResetEnabled']))><span aria-hidden="true"></span><span>{{ __('ui.admin.grand_reset_enabled') }}</span></label>
</div>
<div class="mt-7 grid gap-6 md:grid-cols-2">
    @foreach ([
        'grandResetRequiredLevel' => ['grand_reset_required_level', 'grand_reset_required_level_help', 1, 400, 1],
        'grandResetRequiredResets' => ['grand_reset_required_resets', 'grand_reset_required_resets_help', 1, $settings['maximumResets'], 1],
        'maximumGrandResets' => ['maximum_grand_resets', 'maximum_grand_resets_help', 1, 10000, 1],
        'grandResetBaseZenCost' => ['grand_reset_zen_cost', 'grand_reset_zen_cost_help', 0, 2000000000, 1000000],
        'grandResetPoints' => ['grand_reset_points', 'grand_reset_points_help', 0, 2000000000, 1],
    ] as $field => [$label, $help, $minimum, $maximum, $step])
        <label class="field-group"><span>{{ __('ui.admin.'.$label) }}</span><input type="number" name="{{ $field }}" value="{{ old($field, $settings[$field]) }}" min="{{ $minimum }}" max="{{ $maximum }}" step="{{ $step }}" required><small>{{ __('ui.admin.'.$help) }}</small></label>
    @endforeach
    <label class="field-group"><span>{{ __('ui.admin.grand_reset_points_mode') }}</span><select name="grandResetPointsMode">
        @foreach (['add_fixed' => 'grand_points_add_fixed', 'add_cumulative' => 'grand_points_add_cumulative', 'fixed_cumulative' => 'grand_points_fixed_cumulative', 'fixed' => 'grand_points_fixed'] as $value => $label)
            <option value="{{ $value }}" @selected(old('grandResetPointsMode', $settings['grandResetPointsMode']) === $value)>{{ __('ui.admin.'.$label) }}</option>
        @endforeach
    </select></label>
    <label class="field-group md:col-span-2"><span>{{ __('ui.admin.grand_reset_inventory_policy') }}</span><select name="grandResetInventoryPolicy">
        @foreach (['keep_all' => 'keep_all', 'clear_bag' => 'clear_bag', 'clear_all' => 'clear_all'] as $value => $label)
            <option value="{{ $value }}" @selected(old('grandResetInventoryPolicy', $settings['grandResetInventoryPolicy']) === $value)>{{ __('ui.admin.'.$label) }}</option>
        @endforeach
    </select><small>{{ __('ui.admin.destructive_confirmation') }}</small></label>
</div>
<div class="mt-7 grid gap-3 lg:grid-cols-3">
    @foreach (['grandResetMultiplyCost' => ['grand_reset_scale_cost', 'grand_reset_scale_cost_help'], 'grandResetClearStats' => ['grand_reset_clear_stats', 'grand_reset_clear_stats_help'], 'grandResetConsumeRequiredResets' => ['grand_reset_consume_resets', 'grand_reset_consume_resets_help']] as $field => [$label, $help])
        <label class="check-card"><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings[$field]))><span><strong>{{ __('ui.admin.'.$label) }}</strong><small>{{ __('ui.admin.'.$help) }}</small></span></label>
    @endforeach
</div>
