@props([
    'balance',
    'transactions',
    'showTrigger' => true,
])

<div
    {{ $attributes->class('contents') }}
    x-data="{ walletOpen: @js(request()->query('panel') === 'wallet') }"
    x-effect="document.body.style.overflow = walletOpen ? 'hidden' : ''"
    @open-account-wallet.window="walletOpen = true"
>
    @if ($showTrigger)
        <button type="button" class="resource-chip text-left" x-ref="walletTrigger" @click="walletOpen = true">
            <span>{{ __('ui.account.wallet_credits') }}</span>
            <strong class="text-gold">{{ number_format($balance) }}</strong>
        </button>
    @endif

    <template x-teleport="body">
        <div
            class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-6"
            x-show="walletOpen"
            x-transition.opacity
            x-cloak
            @click.self="walletOpen = false; $nextTick(() => $refs.walletTrigger?.focus())"
            @keydown.escape.window="walletOpen = false"
        >
            <section class="panel-card my-auto w-full max-w-4xl shadow-2xl shadow-black/60" role="dialog" aria-modal="true" aria-labelledby="account-wallet-title">
                <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                    <div>
                        <p class="kicker">{{ __('ui.account.wallet_kicker') }}</p>
                        <h2 id="account-wallet-title" class="mt-2 text-2xl font-semibold text-white">{{ __('ui.account.wallet_heading') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-silver">{{ __('ui.account.wallet_help') }}</p>
                    </div>
                    <button type="button" class="icon-button shrink-0" aria-label="{{ __('ui.account.wallet_close') }}" @click="walletOpen = false; $nextTick(() => $refs.walletTrigger?.focus())">×</button>
                </div>

                <div class="mt-6 rounded-2xl border border-gold/20 bg-gold/8 p-5">
                    <p class="text-xs uppercase tracking-[0.16em] text-steel">{{ __('ui.account.wallet_available') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gold">{{ number_format($balance) }} {{ __('ui.account.wallet_credits') }}</p>
                </div>

                <div class="mt-6 overflow-x-auto rounded-2xl border border-white/8">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-black/20 text-xs uppercase tracking-[0.14em] text-steel">
                            <tr>
                                <th class="px-4 py-3">{{ __('ui.account.wallet_time') }}</th>
                                <th class="px-4 py-3">{{ __('ui.account.wallet_description') }}</th>
                                <th class="px-4 py-3">{{ __('ui.account.wallet_change') }}</th>
                                <th class="px-4 py-3">{{ __('ui.account.wallet_balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/6">
                            @forelse ($transactions as $transaction)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-steel">{{ $transaction->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="min-w-64 px-4 py-3 text-silver">{{ $transaction->reason }}</td>
                                    <td @class(['whitespace-nowrap px-4 py-3 font-semibold', 'text-emerald-300' => $transaction->amount > 0, 'text-red-300' => $transaction->amount < 0])>
                                        {{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-white">{{ number_format($transaction->balance_after) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-steel">{{ __('ui.account.wallet_empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </template>
</div>
