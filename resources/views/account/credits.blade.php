@extends('layouts.app')

@section('title', __('ui.account.credits_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <x-account-feature-header
            :kicker="__('ui.account.wallet_kicker')"
            :heading="__('ui.account.credits_heading')"
            :intro="__('ui.account.credits_intro')"
            :show-back="false"
        />

        <x-account-navigation class="mt-6" />

        <section class="mt-8 grid gap-5 lg:grid-cols-[22rem_minmax(0,1fr)]">
            <div class="panel-card p-6">
                <p class="kicker">{{ __('ui.account.wallet_available') }}</p>
                <p class="mt-4 font-display text-5xl text-white">{{ number_format($balance) }}</p>
                <p class="mt-1 text-sm text-gold">{{ __('ui.account.wallet_credits') }}</p>
                <p class="mt-5 text-sm leading-6 text-steel">{{ __('ui.account.credits_balance_help') }}</p>
            </div>

            <div class="panel-card p-6">
                <p class="kicker">{{ __('ui.account.credits_purchase_kicker') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('ui.account.credits_purchase_heading') }}</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-silver">{{ __('ui.account.credits_purchase_help') }}</p>
                @if ($providers === [])
                    <p class="mt-5 rounded-xl border border-white/8 bg-black/20 p-4 text-sm text-steel">{{ __('ui.account.credits_no_providers') }}</p>
                @else
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        @forelse ($packages as $package)
                            <article class="rounded-2xl border border-white/10 bg-black/20 p-5">
                                <h3 class="text-lg font-semibold text-white">{{ $package->localizedTitle() }}</h3>
                                <p class="mt-3 font-display text-3xl text-gold">{{ number_format($package->credits) }} Credits</p>
                                <p class="mt-1 text-sm text-silver">{{ number_format($package->price_minor / 100, 2) }} {{ $package->currency }}</p>
                                <form method="POST" action="{{ route('account.credit-packages.purchase', $package) }}" class="mt-5 flex gap-3">@csrf
                                    <select name="provider" class="min-w-0 flex-1" required>@foreach ($providers as $provider)<option value="{{ $provider->value }}">{{ ucfirst($provider->value) }}</option>@endforeach</select>
                                    <button class="button button-primary">{{ __('ui.account.credits_buy') }}</button>
                                </form>
                            </article>
                        @empty
                            <p class="text-sm text-steel">{{ __('ui.account.credits_no_packages') }}</p>
                        @endforelse
                    </div>
                @endif
            </div>
        </section>

        @if ($payments->isNotEmpty())
            <section class="mt-10 panel-card overflow-x-auto p-0">
                <div class="p-5"><p class="kicker">{{ __('ui.account.credits_payment_history') }}</p></div>
                <table class="min-w-full text-left text-sm"><thead class="border-y border-white/8 text-xs uppercase tracking-[0.14em] text-steel"><tr><th class="px-5 py-4">{{ __('ui.account.credits_package') }}</th><th class="px-5 py-4">{{ __('ui.account.credits_provider') }}</th><th class="px-5 py-4">{{ __('ui.account.credits_price') }}</th><th class="px-5 py-4">{{ __('ui.account.credits_status') }}</th><th class="px-5 py-4">{{ __('ui.account.wallet_time') }}</th></tr></thead><tbody class="divide-y divide-white/6">@foreach ($payments as $payment)<tr><td class="px-5 py-4 text-white">{{ app()->getLocale() === 'bg' ? $payment->package_snapshot['title_bg'] : $payment->package_snapshot['title_en'] }}</td><td class="px-5 py-4 text-silver">{{ ucfirst($payment->provider->value) }}</td><td class="px-5 py-4 text-silver">{{ number_format($payment->amount_minor / 100, 2) }} {{ $payment->currency }}</td><td class="px-5 py-4 text-silver">{{ __('ui.account.credits_statuses.'.$payment->status->value) }}</td><td class="px-5 py-4 text-steel">{{ $payment->created_at?->format('Y-m-d H:i') }}</td></tr>@endforeach</tbody></table>
            </section>
        @endif

        <section class="mt-10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="kicker">{{ __('ui.account.credits_history_kicker') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.account.credits_history_heading') }}</h2>
                </div>
                <p class="text-sm text-steel">{{ __('ui.account.credits_history_count', ['count' => number_format($transactions->total())]) }}</p>
            </div>

            <div class="panel-card mt-5 overflow-x-auto p-0">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/8 bg-black/20 text-xs uppercase tracking-[0.14em] text-steel">
                        <tr>
                            <th class="px-5 py-4">{{ __('ui.account.wallet_time') }}</th>
                            <th class="px-5 py-4">{{ __('ui.account.wallet_description') }}</th>
                            <th class="px-5 py-4">{{ __('ui.account.wallet_change') }}</th>
                            <th class="px-5 py-4">{{ __('ui.account.wallet_balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/6">
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-steel">{{ $transaction->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="min-w-72 px-5 py-4 text-silver">{{ $transaction->reason }}</td>
                                <td @class([
                                    'whitespace-nowrap px-5 py-4 font-semibold',
                                    'text-emerald-300' => $transaction->amount > 0,
                                    'text-red-300' => $transaction->amount < 0,
                                ])>
                                    {{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-white">{{ number_format($transaction->balance_after) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-steel">{{ __('ui.account.wallet_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($transactions->hasPages())
                <div class="mt-5 flex items-center justify-between gap-4">
                    <a
                        href="{{ $transactions->previousPageUrl() ?? '#' }}"
                        @class(['button button-ghost', 'pointer-events-none opacity-40' => $transactions->onFirstPage()])
                        @if ($transactions->onFirstPage()) aria-disabled="true" @else rel="prev" @endif
                    >{{ __('ui.account.credits_previous') }}</a>
                    <span class="text-sm text-steel">{{ __('ui.account.credits_page', ['current' => $transactions->currentPage(), 'last' => $transactions->lastPage()]) }}</span>
                    <a
                        href="{{ $transactions->nextPageUrl() ?? '#' }}"
                        @class(['button button-ghost', 'pointer-events-none opacity-40' => ! $transactions->hasMorePages()])
                        @if ($transactions->hasMorePages()) rel="next" @else aria-disabled="true" @endif
                    >{{ __('ui.account.credits_next') }}</a>
                </div>
            @endif
        </section>
    </div>
@endsection
