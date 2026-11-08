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
            </div>
        </div>

        <x-account-navigation
            class="mt-6"
            :vip-active-count="$activeVipEntitlementCount"
            :vip-modal-available="$vipEnabled"
        />

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
                <x-account-vaults-panel :show-empty-character-notice="true" />
            </section>
        @else
            <div
                class="mt-6 grid gap-6"
                :class="activeSection === 'vaults' ? 'grid-cols-1' : 'xl:grid-cols-[22rem_1fr]'"
            >
                <x-account-character-portrait x-show="activeSection !== 'vaults'" x-cloak />

                <section class="min-w-0">
                    <x-account-character-tabs x-show="activeSection !== 'vaults'" x-cloak />

                    <x-account-overview-panel />
                    <x-account-inventory-panel />

                    <x-account-vaults-panel />

                    <x-account-stats-panel />

                    <x-account-stat-reset-panel />

                    <x-account-pk-clear-panel />

                    <x-account-move-to-town-panel />

                    <x-account-reset-panel />

                    <x-account-grand-reset-panel />
                </section>
            </div>
        @endif

    </div>
@endsection
