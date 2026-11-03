@props([
    'walletBalance' => null,
    'vipActiveCount' => null,
    'walletModalAvailable' => false,
    'vipModalAvailable' => false,
    'securityModalAvailable' => false,
])

@inject('moduleManager', 'App\Modules\ModuleManager')

@php
    $activePanel = request()->query('panel');
    $activeTab = request()->query('tab');
    $dashboardActive = request()->routeIs('account.dashboard')
        && $activePanel === null
        && $activeTab !== 'vaults';
    $vaultsActive = request()->routeIs('account.web-storage.*')
        || (request()->routeIs('account.dashboard') && $activeTab === 'vaults');
    $walletActive = request()->routeIs('account.dashboard') && $activePanel === 'wallet';
    $vipActive = request()->routeIs('account.dashboard') && $activePanel === 'vip';
    $securityActive = request()->routeIs('account.dashboard') && $activePanel === 'security';
@endphp

<nav
    {{ $attributes->class('rounded-2xl border border-white/8 bg-black/25 p-2 backdrop-blur-md') }}
    aria-label="{{ __('ui.account.navigation.label') }}"
>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <a
            href="{{ route('account.dashboard') }}"
            @class([
                'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 transition',
                'border-gold/35 bg-gold/10 text-white' => $dashboardActive,
                'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! $dashboardActive,
            ])
            @if ($dashboardActive) aria-current="page" @endif
        >
            <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M8 7.5a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" />
                    <path d="M4.5 21a7.5 7.5 0 0 1 15 0" />
                </svg>
            </span>
            <span>
                <strong class="block text-sm">{{ __('ui.account.navigation.characters') }}</strong>
                <span class="mt-0.5 block text-xs text-steel">{{ __('ui.account.navigation.characters_help') }}</span>
            </span>
        </a>

        @if ($moduleManager->enabled(\App\Modules\ServerModule::WebStorage))
            <a
                href="{{ route('account.dashboard', ['tab' => 'vaults']) }}"
                @class([
                    'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 transition',
                    'border-gold/35 bg-gold/10 text-white' => $vaultsActive,
                    'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! $vaultsActive,
                ])
                @if ($vaultsActive) aria-current="page" @endif
            >
                <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M3 9.5 12 4l9 5.5v9A1.5 1.5 0 0 1 19.5 20h-15A1.5 1.5 0 0 1 3 18.5v-9Z" />
                        <path d="M7 12h10M7 16h10" />
                    </svg>
                </span>
                <span>
                    <strong class="block text-sm">{{ __('ui.account.navigation.vaults') }}</strong>
                    <span class="mt-0.5 block text-xs text-steel">{{ __('ui.account.navigation.vaults_help') }}</span>
                </span>
            </a>
        @endif

        @if ($moduleManager->enabled(\App\Modules\ServerModule::Credits))
            @if ($walletModalAvailable)
                <button
                    type="button"
                    @class([
                        'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 text-left transition',
                        'border-gold/35 bg-gold/10 text-white' => $walletActive,
                        'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! $walletActive,
                    ])
                    @click="$dispatch('open-account-wallet')"
                >
            @else
                <a
                    href="{{ route('account.dashboard', ['panel' => 'wallet']) }}"
                    class="group flex min-w-44 flex-1 items-center gap-3 rounded-xl border border-transparent px-4 py-3 text-silver transition hover:border-white/10 hover:bg-white/5 hover:text-white"
                >
            @endif
                <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 6.5h14A2.5 2.5 0 0 1 20.5 9v9A2.5 2.5 0 0 1 18 20.5H4A2.5 2.5 0 0 1 1.5 18V5A2.5 2.5 0 0 1 4 2.5h12" />
                        <path d="M15 11.5h5.5v4H15a2 2 0 1 1 0-4Z" />
                    </svg>
                </span>
                <span>
                    <strong class="block text-sm">{{ __('ui.account.navigation.credits') }}</strong>
                    <span class="mt-0.5 block text-xs text-steel">
                        {{ $walletBalance === null ? __('ui.account.navigation.credits_help') : number_format($walletBalance).' Credits' }}
                    </span>
                </span>
            @if ($walletModalAvailable)
                </button>
            @else
                </a>
            @endif
        @endif

        @if ($moduleManager->enabled(\App\Modules\ServerModule::Vip))
            @if ($vipModalAvailable)
                <button
                    type="button"
                    @class([
                        'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 text-left transition',
                        'border-gold/35 bg-gold/10 text-white' => $vipActive,
                        'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! $vipActive,
                    ])
                    @click="$dispatch('open-account-vip')"
                >
            @else
                <a
                    href="{{ route('account.dashboard', ['panel' => 'vip']) }}"
                    class="group flex min-w-44 flex-1 items-center gap-3 rounded-xl border border-transparent px-4 py-3 text-silver transition hover:border-white/10 hover:bg-white/5 hover:text-white"
                >
            @endif
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m12 3 2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4-3.9-3.8 5.4-.8L12 3Z" />
                        </svg>
                    </span>
                    <span>
                        <strong class="block text-sm">{{ __('ui.account.navigation.vip') }}</strong>
                        <span class="mt-0.5 block text-xs text-steel">
                            {{ $vipActiveCount === null ? __('ui.account.navigation.vip_help') : __('ui.account.navigation.vip_active', ['count' => $vipActiveCount]) }}
                        </span>
                    </span>
            @if ($vipModalAvailable)
                </button>
            @else
                </a>
            @endif
        @endif

        @if ($moduleManager->enabled(\App\Modules\ServerModule::VoteRewards))
            <a
                href="{{ route('votes.index') }}"
                @class([
                    'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 transition',
                    'border-gold/35 bg-gold/10 text-white' => request()->routeIs('votes.*'),
                    'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! request()->routeIs('votes.*'),
                ])
                @if (request()->routeIs('votes.*')) aria-current="page" @endif
            >
                <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M7 3.5h10v17l-5-3-5 3v-17Z" />
                        <path d="m9.5 10.5 1.6 1.6 3.7-4" />
                    </svg>
                </span>
                <span>
                    <strong class="block text-sm">{{ __('ui.account.navigation.vote') }}</strong>
                    <span class="mt-0.5 block text-xs text-steel">{{ __('ui.account.navigation.vote_help') }}</span>
                </span>
            </a>
        @endif

        @if ($moduleManager->enabled(\App\Modules\ServerModule::JewelBank))
            <a
                href="{{ route('account.jewel-bank.index') }}"
                @class([
                    'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 transition',
                    'border-gold/35 bg-gold/10 text-white' => request()->routeIs('account.jewel-bank.*'),
                    'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! request()->routeIs('account.jewel-bank.*'),
                ])
                @if (request()->routeIs('account.jewel-bank.*')) aria-current="page" @endif
            >
                <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="m12 2.5 6.5 6.3L12 21.5 5.5 8.8 12 2.5Z" />
                        <path d="M5.5 8.8h13M9 8.8l3 12.7 3-12.7L12 2.5 9 8.8Z" />
                    </svg>
                </span>
                <span>
                    <strong class="block text-sm">{{ __('ui.account.navigation.jewel_bank') }}</strong>
                    <span class="mt-0.5 block text-xs text-steel">{{ __('ui.account.navigation.jewel_bank_help') }}</span>
                </span>
            </a>
        @endif

        @if ($moduleManager->enabled(\App\Modules\ServerModule::ZenBank))
            <a
                href="{{ route('account.zen-bank.index') }}"
                @class([
                    'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 transition',
                    'border-gold/35 bg-gold/10 text-white' => request()->routeIs('account.zen-bank.*'),
                    'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! request()->routeIs('account.zen-bank.*'),
                ])
                @if (request()->routeIs('account.zen-bank.*')) aria-current="page" @endif
            >
                <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <ellipse cx="12" cy="6" rx="7" ry="3.5" />
                        <path d="M5 6v6c0 1.9 3.1 3.5 7 3.5s7-1.6 7-3.5V6M5 12v6c0 1.9 3.1 3.5 7 3.5s7-1.6 7-3.5v-6" />
                    </svg>
                </span>
                <span>
                    <strong class="block text-sm">{{ __('ui.account.navigation.zen_bank') }}</strong>
                    <span class="mt-0.5 block text-xs text-steel">{{ __('ui.account.navigation.zen_bank_help') }}</span>
                </span>
            </a>
        @endif

        @if ($moduleManager->enabled(\App\Modules\ServerModule::AccountSecurity))
            @if ($securityModalAvailable)
                <button
                    type="button"
                    @class([
                        'group flex min-w-44 flex-1 items-center gap-3 rounded-xl border px-4 py-3 text-left transition',
                        'border-gold/35 bg-gold/10 text-white' => $securityActive,
                        'border-transparent text-silver hover:border-white/10 hover:bg-white/5 hover:text-white' => ! $securityActive,
                    ])
                    @click="$dispatch('open-account-security')"
                >
            @else
                <a
                    href="{{ route('account.dashboard', ['panel' => 'security']) }}"
                    class="group flex min-w-44 flex-1 items-center gap-3 rounded-xl border border-transparent px-4 py-3 text-silver transition hover:border-white/10 hover:bg-white/5 hover:text-white"
                >
            @endif
                <span class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25 text-gold" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 3 5.5 5.7v5.5c0 4.1 2.7 7.8 6.5 9.8 3.8-2 6.5-5.7 6.5-9.8V5.7L12 3Z" />
                        <path d="M9.5 12 11 13.5l3.5-3.5" />
                    </svg>
                </span>
                <span>
                    <strong class="block text-sm">{{ __('ui.account.navigation.security') }}</strong>
                    <span class="mt-0.5 block text-xs text-steel">{{ __('ui.account.navigation.security_help') }}</span>
                </span>
            @if ($securityModalAvailable)
                </button>
            @else
                </a>
            @endif
        @endif
    </div>
</nav>
