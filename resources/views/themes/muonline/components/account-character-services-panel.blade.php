@props([
    'economySettings',
])

<div class="mt-5" x-show="activeSection === 'services'" x-cloak>
    <article class="panel-card">
        <div>
            <p class="kicker">{{ __('ui.account.character_services_eyebrow') }}</p>
            <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.character_services') }}</h3>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-silver">{{ __('ui.account.character_services_help') }}</p>
        </div>

        <div class="mt-7 grid gap-3">
            @if ($economySettings->characterRenameEnabled)
                <section class="flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/3 p-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <h4 class="font-semibold text-white">{{ __('ui.account.character_rename') }}</h4>
                        <p class="mt-1 text-sm leading-6 text-silver">{{ __('ui.account.character_rename_help') }}</p>
                    </div>
                    <strong class="shrink-0 text-sm text-gold">{{ number_format($economySettings->characterRenamePriceCredits) }} {{ __('ui.account.wallet_credits') }}</strong>
                    <button type="button" class="button button-primary shrink-0 justify-center" @click="openCharacterService('rename')">
                        {{ __('ui.account.character_rename_open') }}
                    </button>
                </section>
            @endif

            @if ($economySettings->characterClassChangeEnabled)
                <section class="flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/3 p-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <h4 class="font-semibold text-white">{{ __('ui.account.character_class_change') }}</h4>
                        <p class="mt-1 text-sm leading-6 text-silver">{{ __('ui.account.character_class_change_help') }}</p>
                    </div>
                    <strong class="shrink-0 text-sm text-gold">{{ number_format($economySettings->characterClassChangePriceCredits) }} {{ __('ui.account.wallet_credits') }}</strong>
                    <button type="button" class="button button-primary shrink-0 justify-center" @click="openCharacterService('class-change')">
                        {{ __('ui.account.character_class_change_open') }}
                    </button>
                </section>
            @endif

            @if ($economySettings->characterResetPurchaseEnabled)
                <section class="flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/3 p-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <h4 class="font-semibold text-white">{{ __('ui.account.character_buy_resets') }}</h4>
                        <p class="mt-1 text-sm leading-6 text-silver">{{ __('ui.account.character_buy_resets_help') }}</p>
                    </div>
                    <strong class="shrink-0 text-sm text-gold">{{ number_format($economySettings->characterResetPriceCredits) }} {{ __('ui.account.wallet_credits') }} / {{ __('ui.account.character_one_reset') }}</strong>
                    <button type="button" class="button button-primary shrink-0 justify-center" @click="openCharacterService('buy-resets')">
                        {{ __('ui.account.character_buy_resets_open') }}
                    </button>
                </section>
            @endif

            @if ($economySettings->characterLevelUpPointsPurchaseEnabled)
                <section class="flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/3 p-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <h4 class="font-semibold text-white">{{ __('ui.account.character_buy_level_up_points') }}</h4>
                        <p class="mt-1 text-sm leading-6 text-silver">{{ __('ui.account.character_buy_level_up_points_help') }}</p>
                    </div>
                    <strong class="shrink-0 text-sm text-gold">{{ number_format($economySettings->characterLevelUpPointsPackageSize) }} {{ __('ui.account.character_points') }} = {{ number_format($economySettings->characterLevelUpPointsPriceCredits) }} {{ __('ui.account.wallet_credits') }}</strong>
                    <button type="button" class="button button-primary shrink-0 justify-center" @click="openCharacterService('buy-level-up-points')">
                        {{ __('ui.account.character_buy_level_up_points_open') }}
                    </button>
                </section>
            @endif
        </div>

        <p class="mt-6 text-sm leading-6 text-silver">{{ __('ui.account.character_services_offline_notice') }}</p>
    </article>
</div>
