@extends('layouts.app')

@section('title', __('ui.admin.credit_purchase_history_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <p class="kicker">{{ __('ui.admin.economy') }}</p>
            <h1 class="mt-3 font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.credit_purchase_history_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.credit_purchase_history_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[18rem_minmax(0,1fr)]">
            <x-admin-navigation active="credit-payments" />

            <main class="min-w-0">
                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="grid content-start gap-6">
                    <section class="panel-card p-5 sm:p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <p class="kicker">{{ __('ui.admin.credit_purchase_filters') }}</p>
                            <span class="text-sm text-steel">{{ __('ui.admin.credit_purchase_count', ['count' => $payments->total()]) }}</span>
                        </div>

                        <form method="GET" action="{{ route('admin.credit-payments.index') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[minmax(14rem,1fr)_10rem_10rem_10rem_10rem_auto]">
                            <label class="field-group">
                                <span>{{ __('ui.admin.credit_purchase_search') }}</span>
                                <input type="search" name="search" value="{{ $paymentFilters['search'] }}" maxlength="120" placeholder="{{ __('ui.admin.credit_purchase_search_placeholder') }}">
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.credit_status') }}</span>
                                <select name="status">
                                    <option value="">{{ __('ui.admin.credit_all_statuses') }}</option>
                                    @foreach (\App\Payments\CreditPaymentStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected($paymentFilters['status'] === $status->value)>
                                            {{ __('ui.admin.credit_payment_statuses.'.$status->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.credit_provider') }}</span>
                                <select name="provider">
                                    <option value="">{{ __('ui.admin.credit_all_providers') }}</option>
                                    @foreach (\App\Payments\PaymentProvider::cases() as $provider)
                                        <option value="{{ $provider->value }}" @selected($paymentFilters['provider'] === $provider->value)>
                                            {{ ucfirst($provider->value) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.date_from') }}</span>
                                <input type="date" name="date_from" value="{{ $paymentFilters['dateFrom'] }}">
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.date_to') }}</span>
                                <input type="date" name="date_to" value="{{ $paymentFilters['dateTo'] }}">
                            </label>
                            <div class="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                                <button type="submit" class="button button-primary">{{ __('ui.admin.credit_purchase_filter') }}</button>
                                <a href="{{ route('admin.credit-payments.index') }}" class="button button-ghost">{{ __('ui.admin.credit_purchase_clear') }}</a>
                            </div>
                        </form>
                    </section>

                    <section class="panel-card overflow-hidden">
                        <div class="border-b border-white/8 p-5 sm:p-6">
                            <p class="kicker">{{ __('ui.admin.credit_payment_history') }}</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                                <tr>
                                    <th class="px-5 py-4">{{ __('ui.admin.wallet_account_id') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.credit_package') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.credit_provider') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.credit_reference') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.credit_price') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.credit_status') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.wallet_time') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/6">
                                @forelse ($payments as $payment)
                                    @php($packageTitle = app()->getLocale() === 'bg' ? ($payment->package_snapshot['title_bg'] ?? null) : ($payment->package_snapshot['title_en'] ?? null))
                                    @php($paymentReference = $payment->provider_transaction_id ?? $payment->provider_order_id ?? $payment->public_id)
                                    <tr>
                                        <td class="px-5 py-4 font-semibold text-white">{{ $payment->account_id }}</td>
                                        <td class="px-5 py-4">
                                            <span class="block text-white">{{ $packageTitle ?? __('ui.admin.credit_unknown_package') }}</span>
                                            <span class="mt-1 block whitespace-nowrap text-xs text-gold">{{ number_format($payment->credits) }} Credits</span>
                                        </td>
                                        <td class="px-5 py-4 text-silver">{{ ucfirst($payment->provider->value) }}</td>
                                        <td class="max-w-56 break-all px-5 py-4 font-mono text-xs text-steel">{{ $paymentReference }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-silver">{{ number_format($payment->amount_minor / 100, 2) }} {{ $payment->currency }}</td>
                                        <td class="px-5 py-4">
                                            <span @class([
                                                'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                'border-amber-300/25 bg-amber-300/8 text-amber-100' => $payment->status === \App\Payments\CreditPaymentStatus::Pending,
                                                'border-emerald-300/25 bg-emerald-300/8 text-emerald-100' => $payment->status === \App\Payments\CreditPaymentStatus::Paid,
                                                'border-red-300/25 bg-red-300/8 text-red-100' => $payment->status === \App\Payments\CreditPaymentStatus::Failed,
                                                'border-sky-300/25 bg-sky-300/8 text-sky-100' => $payment->status === \App\Payments\CreditPaymentStatus::Refunded,
                                            ])>
                                                {{ __('ui.admin.credit_payment_statuses.'.$payment->status->value) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-steel">{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.credit_payments_empty') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            </table>
                        </div>

                        @if ($payments->hasPages())
                            <div class="border-t border-white/8 p-5">{{ $payments->links() }}</div>
                        @endif
                    </section>
                </div>
            </main>
        </div>
    </div>
@endsection
