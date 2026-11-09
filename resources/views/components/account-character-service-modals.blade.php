@props([
    'economySettings',
    'walletBalance',
    'characterRenameIdempotencyKey',
    'characterClassChangeIdempotencyKey',
])

<template x-teleport="body">
    <div
        class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-6"
        x-show="characterServiceModal !== null"
        x-transition.opacity
        x-cloak
        @click.self="closeCharacterService()"
        @keydown.escape.window="characterServiceConfirmationOpen ? closeCharacterServiceConfirmation() : closeCharacterService()"
    >
        <section
            class="panel-card my-auto w-full max-w-2xl shadow-2xl shadow-black/60"
            role="dialog"
            aria-modal="true"
            aria-labelledby="character-service-modal-title"
        >
            <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                <div>
                    <p class="kicker">{{ __('ui.account.character_services_eyebrow') }}</p>
                    <h2 id="character-service-modal-title" class="mt-2 text-2xl font-semibold text-white">
                        <span x-show="characterServiceModal === 'rename'">{{ __('ui.account.character_rename') }}</span>
                        <span x-show="characterServiceModal === 'class-change'">{{ __('ui.account.character_class_change') }}</span>
                    </h2>
                </div>
                <button type="button" class="icon-button shrink-0" aria-label="{{ __('ui.account.character_services_close') }}" @click="closeCharacterService()">×</button>
            </div>

            @error('character_service')
                <div class="mt-5 rounded-xl border border-red-300/25 bg-red-300/8 px-4 py-3 text-sm text-red-100" role="alert">
                    {{ $message }}
                </div>
            @enderror

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="outcome-card">
                    <span>{{ __('ui.account.character_selected') }}</span>
                    <strong x-text="selectedCharacter.name"></strong>
                </div>
                <div class="outcome-card">
                    <span>{{ __('ui.account.character_current_balance') }}</span>
                    <strong>{{ number_format($walletBalance) }} <small class="outcome-unit">{{ __('ui.account.wallet_credits') }}</small></strong>
                </div>
                <div class="outcome-card">
                    <span>{{ __('ui.account.character_service_price') }}</span>
                    @if ($economySettings->characterRenameEnabled)
                        <strong x-show="characterServiceModal === 'rename'">{{ number_format($economySettings->characterRenamePriceCredits) }} <small class="outcome-unit">{{ __('ui.account.wallet_credits') }}</small></strong>
                    @endif
                    @if ($economySettings->characterClassChangeEnabled)
                        <strong x-show="characterServiceModal === 'class-change'">{{ number_format($economySettings->characterClassChangePriceCredits) }} <small class="outcome-unit">{{ __('ui.account.wallet_credits') }}</small></strong>
                    @endif
                </div>
            </div>

            <div
                class="mt-4 rounded-2xl border p-4 text-sm leading-6"
                :class="selectedCharacter.online ? 'border-amber-300/20 bg-amber-300/7 text-amber-100' : 'border-emerald-300/20 bg-emerald-300/7 text-emerald-100'"
            >
                <strong class="block" x-text="selectedCharacter.online ? @js(__('ui.account.character_offline_blocked')) : @js(__('ui.account.character_offline_ready'))"></strong>
                <span>{{ __('ui.account.character_services_offline_notice') }}</span>
            </div>

            @if ($economySettings->characterRenameEnabled)
                <form
                    method="POST"
                    action="{{ route('account.character-rename') }}"
                    class="mt-6 grid gap-5"
                    x-show="characterServiceModal === 'rename'"
                    x-ref="characterRenameForm"
                    @submit.prevent="openCharacterServiceConfirmation()"
                >
                    @csrf
                    <input type="hidden" name="character_name" :value="selectedCharacter.name">
                    <input type="hidden" name="idempotency_key" value="{{ $characterRenameIdempotencyKey }}">

                    <div>
                        <p class="text-sm leading-6 text-silver">{{ __('ui.account.character_rename_help') }}</p>
                        <label class="field-group mt-4">
                            {{ __('ui.account.character_new_name') }}
                            <input
                                type="text"
                                name="new_name"
                                x-model="characterRenameNewName"
                                minlength="4"
                                maxlength="10"
                                pattern="[A-Za-z0-9_]+"
                                required
                            >
                        </label>
                        @error('new_name')
                            <p class="mt-2 text-sm text-red-200" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs leading-5 text-silver">{{ __('ui.account.character_name_rules') }}</p>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-white/8 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-steel">{{ __('ui.account.character_rename_confirm_help') }}</p>
                        <button
                            type="submit"
                            class="button button-primary shrink-0 justify-center"
                            x-show="!characterServiceConfirmationOpen"
                            :disabled="!resetPreviewConfiguration.writesEnabled || selectedCharacter.online || {{ $walletBalance }} < {{ $economySettings->characterRenamePriceCredits }}"
                        >{{ __('ui.account.character_rename_review') }}</button>
                    </div>
                </form>
            @endif

            @if ($economySettings->characterClassChangeEnabled)
                <form
                    method="POST"
                    action="{{ route('account.character-class-change') }}"
                    class="mt-6 grid gap-5"
                    x-show="characterServiceModal === 'class-change'"
                    x-ref="characterClassChangeForm"
                    @submit.prevent="openCharacterServiceConfirmation()"
                >
                    @csrf
                    <input type="hidden" name="character_name" :value="selectedCharacter.name">
                    <input type="hidden" name="idempotency_key" value="{{ $characterClassChangeIdempotencyKey }}">

                    <div>
                        <p class="text-sm leading-6 text-silver">{{ __('ui.account.character_class_change_help') }}</p>
                        <label class="field-group mt-4">
                            {{ __('ui.account.character_target_class') }}
                            <select name="target_family" x-model="characterClassTargetFamily" required>
                                <option value="">{{ __('ui.account.character_choose_class') }}</option>
                                <template x-for="option in selectedCharacter.classChangeOptions" :key="option.family">
                                    <option :value="option.family" x-text="option.name"></option>
                                </template>
                            </select>
                        </label>
                        @error('target_family')
                            <p class="mt-2 text-sm text-red-200" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-xs leading-5 text-silver">{{ __('ui.account.character_class_tier_notice') }}</p>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-white/8 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-steel">{{ __('ui.account.character_class_change_confirm_help') }}</p>
                        <button
                            type="submit"
                            class="button button-primary shrink-0 justify-center"
                            x-show="!characterServiceConfirmationOpen"
                            :disabled="!resetPreviewConfiguration.writesEnabled || selectedCharacter.online || {{ $walletBalance }} < {{ $economySettings->characterClassChangePriceCredits }}"
                        >{{ __('ui.account.character_class_change_review') }}</button>
                    </div>
                </form>
            @endif

            <div
                class="mt-6 rounded-2xl border border-gold/20 bg-gold/5 p-5"
                x-show="characterServiceConfirmationOpen"
                x-cloak
            >
                <strong class="block text-white">{{ __('ui.account.character_service_confirm') }}</strong>
                <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.character_service_confirm_help') }}</p>

                <dl class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="outcome-card">
                        <dt>{{ __('ui.account.character_selected') }}</dt>
                        <dd x-text="selectedCharacter.name"></dd>
                    </div>
                    <div class="outcome-card" x-show="characterServiceModal === 'rename'">
                        <dt>{{ __('ui.account.character_new_name') }}</dt>
                        <dd x-text="characterRenameNewName"></dd>
                    </div>
                    <div class="outcome-card" x-show="characterServiceModal === 'class-change'">
                        <dt>{{ __('ui.account.character_target_class') }}</dt>
                        <dd x-text="selectedCharacterClassOption?.name ?? ''"></dd>
                    </div>
                    <div class="outcome-card">
                        <dt>{{ __('ui.account.character_service_price') }}</dt>
                        @if ($economySettings->characterRenameEnabled)
                            <dd x-show="characterServiceModal === 'rename'">{{ number_format($economySettings->characterRenamePriceCredits) }} <small class="outcome-unit">{{ __('ui.account.wallet_credits') }}</small></dd>
                        @endif
                        @if ($economySettings->characterClassChangeEnabled)
                            <dd x-show="characterServiceModal === 'class-change'">{{ number_format($economySettings->characterClassChangePriceCredits) }} <small class="outcome-unit">{{ __('ui.account.wallet_credits') }}</small></dd>
                        @endif
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" class="button button-ghost" :disabled="characterServiceSubmitting" @click="closeCharacterServiceConfirmation()">
                        {{ __('ui.account.reset_cancel') }}
                    </button>
                    <button type="button" class="button button-primary" :disabled="characterServiceSubmitting" @click="submitCharacterService()">
                        <span x-show="!characterServiceSubmitting">{{ __('ui.account.character_service_confirm_action') }}</span>
                        <span x-show="characterServiceSubmitting">{{ __('ui.account.character_service_submitting') }}</span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>
