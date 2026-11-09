<section class="mt-8 border-t border-white/8 pt-7">
    <div>
        <p class="kicker">{{ __('ui.admin.reset_reward_heading') }}</p>
        <p class="mt-2 text-sm text-steel">{{ __('ui.admin.reset_reward_help') }}</p>
    </div>

    <div class="mt-5 grid gap-6 md:grid-cols-2">
        <label class="field-group">
            <span>{{ __('ui.admin.reset_reward_type') }}</span>
            <select name="{{ $settingsPrefix }}Type">
                @foreach (['none' => 'reset_reward_none', 'credits' => 'reset_reward_credits', 'mystery_box' => 'reset_reward_mystery_box'] as $value => $label)
                    <option value="{{ $value }}" @selected(old($settingsPrefix.'Type', $settings[$settingsPrefix.'Type']) === $value)>{{ __('ui.admin.'.$label) }}</option>
                @endforeach
            </select>
        </label>

        <label class="field-group">
            <span>{{ __('ui.admin.reset_reward_credit_amount') }}</span>
            <input
                type="number"
                name="{{ $settingsPrefix }}Credits"
                value="{{ old($settingsPrefix.'Credits', $settings[$settingsPrefix.'Credits']) }}"
                min="1"
                max="9000000000000000"
            >
            <small>{{ __('ui.admin.reset_reward_credit_amount_help') }}</small>
        </label>

        <label class="field-group md:col-span-2">
            <span>{{ __('ui.admin.reset_reward_box') }}</span>
            <select name="{{ $settingsPrefix }}ShopOfferId">
                <option value="">{{ __('ui.admin.reset_reward_select_box') }}</option>
                @foreach ($mysteryBoxes as $mysteryBox)
                    <option value="{{ $mysteryBox->getKey() }}" @selected((string) old($settingsPrefix.'ShopOfferId', $settings[$settingsPrefix.'ShopOfferId']) === (string) $mysteryBox->getKey())>
                        {{ $mysteryBox->localizedTitle(app()->getLocale()) }} · {{ $mysteryBox->status->value }}
                    </option>
                @endforeach
            </select>
            <small>{{ __('ui.admin.reset_reward_box_help') }}</small>
        </label>
    </div>
</section>
