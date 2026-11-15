@inject('moduleManager', 'App\Modules\ModuleManager')
@extends('layouts.app')

@section('title', __('ui.account.title'))

@section('content')
    <div
        class="shell py-10 lg:py-14"
        x-data="accountPanel(
            {{ Js::from($characters) }},
            {{ Js::from($accountPanelConfiguration) }}
        )"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.account.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.account.heading') }}</h1>
                <p class="mt-3 max-w-2xl text-silver">{{ __('ui.account.intro') }}</p>
            </div>
            <div class="flex flex-wrap items-stretch gap-3">
                <div class="resource-chip">
                    <span>{{ __('ui.account.signed_in_as') }}</span>
                    <strong>
                        {{ $account->username }}
                        @if ($moduleManager->enabled(\App\Modules\ServerModule::Credits))
                            <span class="text-gold">· {{ number_format($walletBalance) }} {{ __('ui.account.wallet_credits') }}</span>
                        @endif
                    </strong>
                </div>
                @if ($luckyCoinBalance !== null)
                    <div class="resource-chip">
                        <span>{{ __('ui.account.lucky_coin_balance') }}</span>
                        <strong class="text-gold">{{ number_format($luckyCoinBalance) }} {{ __('ui.account.lucky_coins') }}</strong>
                    </div>
                @endif
                @if ($nativeCurrencyBalances !== null)
                    @foreach (['WCoin(C)' => $nativeCurrencyBalances->wCoinC, 'WCoin(P)' => $nativeCurrencyBalances->wCoinP, 'GoblinPoint' => $nativeCurrencyBalances->goblinPoints] as $currencyLabel => $balance)
                        <div class="resource-chip">
                            <span>{{ $currencyLabel }}</span>
                            <strong class="text-gold">{{ number_format($balance) }}</strong>
                        </div>
                    @endforeach
                @endif
                @if ($nativeAccountMembership !== null)
                    <div class="resource-chip">
                        <span>{{ __('ui.account.native_membership_heading') }}</span>
                        <strong class="text-gold">
                            @if ($nativeAccountMembership->effectiveLevel === null)
                                {{ __('ui.account.native_membership_unknown') }}
                            @elseif ($nativeAccountMembership->effectiveLevel === 0)
                                {{ __('ui.account.native_membership_standard') }}
                            @else
                                {{ __('ui.account.native_membership_tier', ['level' => $nativeAccountMembership->effectiveLevel]) }}
                            @endif
                        </strong>
                        @if (($nativeAccountMembership->storedLevel ?? 0) > 0 && $nativeAccountMembership->expiresAt !== null)
                            <span class="text-xs text-steel">
                                {{ __('ui.account.native_membership_expiry', ['date' => $nativeAccountMembership->expiresAt]) }}
                            </span>
                        @endif
                        @if ($nativeAccountMembership->expired)
                            <span class="text-xs text-steel">{{ __('ui.account.native_membership_expired') }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <x-account-navigation
            class="mt-6"
            :vip-active-count="$activeVipEntitlementCount"
            :vip-modal-available="$vipEnabled"
        />

        @if ($battleCore !== null)
            <x-account-battle-core-panel :snapshot="$battleCore" />
        @endif

        @if ($nativeIdentityServices)
            <p class="panel-card mt-6 p-4 text-sm text-silver">{{ __('ui.account.native_identity_help') }}</p>
        @endif
        @if ($pendingCharacterPurchases->isNotEmpty())
            <section class="panel-card mt-6 p-5" aria-labelledby="pending-character-purchases">
                <h2 id="pending-character-purchases" class="text-lg font-semibold text-white">{{ __('ui.account.pending_purchases_heading') }}</h2>
                <p class="mt-3 text-sm text-silver">{{ __('ui.account.pending_purchases_help') }}</p>
                <div class="mt-4 grid gap-3">
                    @foreach ($pendingCharacterPurchases as $purchase)
                        @php
                            $isResetPurchase = $purchase->service_type === \App\CharacterServices\CharacterServiceType::ResetPurchase;
                            $purchaseRoute = match ($purchase->service_type) {
                                \App\CharacterServices\CharacterServiceType::Rename => 'account.character-rename',
                                \App\CharacterServices\CharacterServiceType::ClassChange => 'account.character-class-change',
                                \App\CharacterServices\CharacterServiceType::ResetPurchase => 'account.character-reset-purchases.store',
                                \App\CharacterServices\CharacterServiceType::LevelUpPointsPurchase => 'account.character-level-up-points-purchases.store',
                            };
                        @endphp
                        <form method="POST" action="{{ route($purchaseRoute) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 p-4">
                            @csrf
                            <input type="hidden" name="character_name" value="{{ $purchase->character_name }}">
                            <input type="hidden" name="idempotency_key" value="{{ $purchase->idempotency_key }}">
                            <input type="hidden" name="new_name" value="{{ $purchase->requested_name }}">
                            <input type="hidden" name="target_family" value="{{ $characterClassDefinitions->get($purchase->target_class_code)?->family }}">
                            <input type="hidden" name="package_count" value="{{ $isResetPurchase ? $purchase->requested_amount : ($purchase->settings_snapshot['package_count'] ?? 0) }}">
                            <p class="text-sm text-silver">
                                <strong class="text-white">{{ $purchase->character_name }}</strong>
                                · {{ __('ui.account.character_'.match ($purchase->service_type) { \App\CharacterServices\CharacterServiceType::Rename => 'rename', \App\CharacterServices\CharacterServiceType::ClassChange => 'class_change', \App\CharacterServices\CharacterServiceType::ResetPurchase => 'buy_resets', \App\CharacterServices\CharacterServiceType::LevelUpPointsPurchase => 'buy_level_up_points' }) }}
                                · {{ number_format($purchase->requested_amount) }}
                            </p>
                            <button type="submit" class="button button-ghost" @disabled(! $gameWritesEnabled)>{{ __('ui.account.pending_purchases_resume') }}</button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($vipEnabled)
            <x-account-vip-modal
                :characters="$characters"
                :plans="$vipPlans"
                :entitlements="$vipEntitlements"
                :active-entitlement-count="$activeVipEntitlementCount"
                :purchases="$vipPurchases"
                :balance="$walletBalance"
                :idempotency-key="$vipIdempotencyKey"
                :game-writes-enabled="$gameWritesEnabled"
                :show-trigger="false"
            />
        @endif
        @if (session('accountStatus'))
            <div class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 px-5 py-4 text-sm text-emerald-100" role="status">
                {{ session('accountStatus') }}
            </div>
        @endif

        @if ($characters !== [])
            <x-account-character-selector
                class="mt-8"
                x-show="activeSection !== 'vaults'"
                x-cloak
            />
        @endif

        @if ($characters === [])
            <section class="mt-6 min-w-0">
                @if ($readOnlyGameProfile)
                    <p class="text-silver">{{ __('ui.account.no_characters_heading') }}</p>
                @else
                    <x-account-vaults-panel :show-empty-character-notice="true" />
                @endif
            </section>
        @else
            <div
                class="mt-6 grid gap-6"
                :class="activeSection === 'vaults' ? 'grid-cols-1' : 'xl:grid-cols-[22rem_1fr]'"
            >
                <x-account-character-portrait x-show="activeSection !== 'vaults'" x-cloak />

                <section class="min-w-0">
                        <x-account-character-tabs
                        :character-services-enabled="$characterServicesEnabled"
                        :read-only="$readOnlyGameProfile"
                        x-show="activeSection !== 'vaults' || {{ $readOnlyGameProfile ? 'true' : 'false' }}"
                        x-cloak
                        />

                    <x-account-overview-panel :economy-settings="$economySettings" :character-services-enabled="$characterServicesEnabled" :read-only="$readOnlyGameProfile" />
                    <x-account-inventory-panel />

                    <x-account-vaults-panel :read-only="$readOnlyGameProfile" />

                    @if (! $readOnlyGameProfile)
                    <x-account-stats-panel />

                    <x-account-stat-reset-panel />

                    <x-account-pk-clear-panel />

                    <x-account-move-to-town-panel />

                    <x-account-reset-panel />

                    <x-account-grand-reset-panel />
                    @endif

                    @if ($characterServicesEnabled)
                        <x-account-character-services-panel
                            :economy-settings="$economySettings"
                        />
                    @endif
                </section>
            </div>

            @if ($characterServicesEnabled)
                <x-account-character-service-modals
                    :economy-settings="$economySettings"
                    :wallet-balance="$walletBalance"
                    :character-rename-idempotency-key="$characterRenameIdempotencyKey"
                    :character-class-change-idempotency-key="$characterClassChangeIdempotencyKey"
                    :character-reset-purchase-idempotency-key="$characterResetPurchaseIdempotencyKey"
                    :character-level-up-points-purchase-idempotency-key="$characterLevelUpPointsPurchaseIdempotencyKey"
                />
            @endif
        @endif

    </div>
@endsection
