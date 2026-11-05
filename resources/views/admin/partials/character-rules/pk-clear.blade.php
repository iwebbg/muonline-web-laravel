<div class="flex items-start justify-between gap-4 border-b border-white/8 pb-6">
    <div><p class="kicker">{{ __('ui.admin.pk_clear_profile') }}</p><p class="mt-2 text-sm text-steel">{{ __('ui.admin.pk_clear_profile_help') }}</p></div>
    <label class="toggle-control"><input type="hidden" name="pkClearEnabled" value="0"><input type="checkbox" name="pkClearEnabled" value="1" @checked(old('pkClearEnabled', $settings['pkClearEnabled']))><span aria-hidden="true"></span><span>{{ __('ui.admin.pk_clear_enabled') }}</span></label>
</div>
<div class="mt-7 grid gap-6 md:grid-cols-3">
    <label class="field-group"><span>{{ __('ui.admin.pk_clear_required_level') }}</span><input type="number" name="pkClearRequiredLevel" value="{{ old('pkClearRequiredLevel', $settings['pkClearRequiredLevel']) }}" min="1" max="400" required><small>{{ __('ui.admin.pk_clear_required_level_help') }}</small></label>
    <label class="field-group"><span>{{ __('ui.admin.pk_clear_base_zen_cost') }}</span><input type="number" name="pkClearBaseZenCost" value="{{ old('pkClearBaseZenCost', $settings['pkClearBaseZenCost']) }}" min="0" max="2000000000" step="100000" required><small>{{ __('ui.admin.pk_clear_base_zen_cost_help') }}</small></label>
    <label class="field-group"><span>{{ __('ui.admin.pk_clear_price_mode') }}</span><select name="pkClearPriceMode">
        @foreach (['fixed' => 'pk_clear_price_fixed', 'by_pk_level' => 'pk_clear_price_by_level', 'by_pk_count' => 'pk_clear_price_by_count'] as $value => $label)
            <option value="{{ $value }}" @selected(old('pkClearPriceMode', $settings['pkClearPriceMode']) === $value)>{{ __('ui.admin.'.$label) }}</option>
        @endforeach
    </select><small>{{ __('ui.admin.pk_clear_price_mode_help') }}</small></label>
</div>
