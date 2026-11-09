<div class="mt-5" x-show="activeSection === 'services'" x-cloak>
    <article class="panel-card">
        <div>
            <p class="kicker">{{ __('ui.account.character_services_eyebrow') }}</p>
            <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.character_services') }}</h3>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-silver">{{ __('ui.account.character_services_help') }}</p>
        </div>

        @error('character_service')
            <div class="mt-6 rounded-2xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100" role="alert">
                {{ $message }}
            </div>
        @enderror

        <div class="mt-7 grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-white/10 bg-white/3 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-white">{{ __('ui.account.character_rename') }}</h4>
                        <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.character_rename_help') }}</p>
                    </div>
                    <span class="preview-badge">{{ number_format($economySettings->characterRenamePriceCredits) }} {{ __('ui.account.wallet_credits') }}</span>
                </div>

                @if ($economySettings->characterRenameEnabled)
                    <form method="POST" action="{{ route('account.character-rename') }}" class="mt-5 grid gap-4">
                        @csrf
                        <input type="hidden" name="character_name" :value="selectedCharacter.name">
                        <input type="hidden" name="idempotency_key" value="{{ $characterRenameIdempotencyKey }}">
                        <label class="field-group">
                            {{ __('ui.account.character_new_name') }}
                            <input
                                type="text"
                                name="new_name"
                                value="{{ old('new_name') }}"
                                minlength="4"
                                maxlength="10"
                                pattern="[A-Za-z0-9_]+"
                                required
                            >
                        </label>
                        <p class="text-xs leading-5 text-silver">{{ __('ui.account.character_name_rules') }}</p>
                        <button
                            type="submit"
                            class="button button-primary justify-center"
                            :disabled="!resetPreviewConfiguration.writesEnabled || selectedCharacter.online || {{ $walletBalance }} < {{ $economySettings->characterRenamePriceCredits }}"
                        >{{ __('ui.account.character_rename_action') }}</button>
                    </form>
                @else
                    <p class="mt-5 rounded-2xl border border-white/10 bg-black/15 p-4 text-sm text-silver">{{ __('ui.account.character_service_disabled') }}</p>
                @endif
            </section>

            <section class="rounded-2xl border border-white/10 bg-white/3 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-white">{{ __('ui.account.character_class_change') }}</h4>
                        <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.character_class_change_help') }}</p>
                    </div>
                    <span class="preview-badge">{{ number_format($economySettings->characterClassChangePriceCredits) }} {{ __('ui.account.wallet_credits') }}</span>
                </div>

                @if ($economySettings->characterClassChangeEnabled)
                    <form method="POST" action="{{ route('account.character-class-change') }}" class="mt-5 grid gap-4">
                        @csrf
                        <input type="hidden" name="character_name" :value="selectedCharacter.name">
                        <input type="hidden" name="idempotency_key" value="{{ $characterClassChangeIdempotencyKey }}">
                        <label class="field-group">
                            {{ __('ui.account.character_target_class') }}
                            <select name="target_family" required>
                                <option value="">{{ __('ui.account.character_choose_class') }}</option>
                                <template x-for="option in selectedCharacter.classChangeOptions" :key="option.family">
                                    <option :value="option.family" x-text="option.name"></option>
                                </template>
                            </select>
                        </label>
                        <p class="text-xs leading-5 text-silver">{{ __('ui.account.character_class_tier_notice') }}</p>
                        <button
                            type="submit"
                            class="button button-primary justify-center"
                            :disabled="!resetPreviewConfiguration.writesEnabled || selectedCharacter.online || {{ $walletBalance }} < {{ $economySettings->characterClassChangePriceCredits }}"
                        >{{ __('ui.account.character_class_change_action') }}</button>
                    </form>
                @else
                    <p class="mt-5 rounded-2xl border border-white/10 bg-black/15 p-4 text-sm text-silver">{{ __('ui.account.character_service_disabled') }}</p>
                @endif
            </section>
        </div>

        <p class="mt-6 text-sm leading-6 text-silver">{{ __('ui.account.character_services_offline_notice') }}</p>
    </article>
</div>
