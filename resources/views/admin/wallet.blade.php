@extends('layouts.app')

@section('title', __('ui.admin.wallet_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.wallet_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.wallet_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="economy" />

            <main class="grid min-w-0 content-start gap-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <section class="panel-card p-5 sm:p-7">
                    <p class="kicker">{{ __('ui.admin.wallet_lookup') }}</p>
                    <form method="GET" action="{{ route('admin.wallet.index') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <label class="field-group flex-1">
                            <span>{{ __('ui.admin.wallet_account_id') }}</span>
                            <input
                                type="text"
                                name="account"
                                value="{{ $requestedAccountId }}"
                                minlength="4"
                                maxlength="10"
                                pattern="[A-Za-z0-9_-]+"
                                required
                            >
                        </label>
                        <button type="submit" class="button button-ghost self-end">{{ __('ui.admin.wallet_search') }}</button>
                    </form>
                </section>

                @if ($accountId !== null)
                    <section
                        class="panel-card p-5 sm:p-7"
                        x-data="walletManager({{ $wallet?->balance ?? 0 }})"
                        x-on:keydown.escape.window="closeConfirmation()"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.admin.wallet_account') }}</p>
                                <h2 class="mt-2 text-2xl font-semibold text-white">{{ $accountId }}</h2>
                            </div>
                            <div class="rounded-2xl border border-gold/20 bg-gold/8 px-5 py-3 text-right">
                                <p class="text-xs uppercase tracking-[0.16em] text-steel">{{ __('ui.admin.wallet_balance') }}</p>
                                <p class="mt-1 text-2xl font-bold text-gold">{{ number_format($wallet?->balance ?? 0) }}</p>
                            </div>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('admin.wallet.store') }}"
                            class="mt-6 grid gap-5"
                            x-ref="walletForm"
                            x-on:submit.prevent="openConfirmation()"
                        >
                            @csrf
                            <input type="hidden" name="account_id" value="{{ $accountId }}">
                            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                            <div class="grid gap-5 md:grid-cols-2">
                                <label class="field-group">
                                    <span>{{ __('ui.admin.wallet_direction') }}</span>
                                    <select name="direction" x-model="direction">
                                        <option value="credit">{{ __('ui.admin.wallet_credit') }}</option>
                                        <option value="debit">{{ __('ui.admin.wallet_debit') }}</option>
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.wallet_amount') }}</span>
                                    <input type="number" name="amount" min="1" max="1000000000" required x-model.number="amount">
                                </label>
                            </div>

                            <label class="field-group">
                                <span>{{ __('ui.admin.wallet_reason') }}</span>
                                <textarea name="reason" minlength="5" maxlength="255" rows="3" required>{{ old('reason') }}</textarea>
                            </label>

                            <div class="flex flex-col gap-4 rounded-2xl border border-white/8 bg-black/20 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.16em] text-steel">{{ __('ui.admin.wallet_preview_balance') }}</p>
                                    <p class="mt-1 text-xl font-semibold" x-bind:class="previewBalance < 0 ? 'text-red-300' : 'text-white'" x-text="previewBalance.toLocaleString()"></p>
                                </div>
                                <button type="submit" class="button button-primary">{{ __('ui.admin.wallet_review') }}</button>
                            </div>
                        </form>

                        <template x-teleport="body">
                            <div x-cloak x-show="confirmationOpen" class="fixed inset-0 z-[120] grid place-items-center bg-black/80 p-4 backdrop-blur-sm" x-on:click.self="closeConfirmation()" role="dialog" aria-modal="true">
                                <section class="panel-card w-full max-w-lg p-6 sm:p-8">
                                <p class="kicker">{{ __('ui.admin.wallet_confirm') }}</p>
                                <h3 class="mt-3 text-2xl font-semibold text-white">{{ $accountId }}</h3>
                                <p class="mt-4 text-silver">{{ __('ui.admin.wallet_confirm_help') }}</p>
                                <div class="mt-5 rounded-2xl border border-white/8 bg-black/20 p-4">
                                    <p class="text-sm text-steel">{{ __('ui.admin.wallet_result_balance') }}</p>
                                    <p class="mt-1 text-2xl font-bold text-gold" x-text="previewBalance.toLocaleString()"></p>
                                </div>
                                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <button type="button" class="button button-ghost" x-on:click="closeConfirmation()">{{ __('ui.admin.news_cancel') }}</button>
                                    <button type="button" class="button button-primary" x-on:click="submitAdjustment()">{{ __('ui.admin.wallet_apply') }}</button>
                                </div>
                                </section>
                            </div>
                        </template>
                    </section>

                    <section class="panel-card overflow-hidden">
                        <div class="border-b border-white/8 p-5 sm:p-6">
                            <p class="kicker">{{ __('ui.admin.wallet_history') }}</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                                    <tr>
                                        <th class="px-5 py-4">{{ __('ui.admin.wallet_time') }}</th>
                                        <th class="px-5 py-4">{{ __('ui.admin.wallet_type') }}</th>
                                        <th class="px-5 py-4">{{ __('ui.admin.wallet_amount') }}</th>
                                        <th class="px-5 py-4">{{ __('ui.admin.wallet_result_balance') }}</th>
                                        <th class="px-5 py-4">{{ __('ui.admin.wallet_reason') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/6">
                                    @forelse ($transactions as $transaction)
                                        <tr>
                                            <td class="whitespace-nowrap px-5 py-4 text-steel">{{ $transaction->created_at?->format('Y-m-d H:i:s') }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-white">{{ __('ui.admin.wallet_types.'.$transaction->type->value) }}</td>
                                            <td @class(['whitespace-nowrap px-5 py-4 font-semibold', 'text-emerald-300' => $transaction->amount > 0, 'text-red-300' => $transaction->amount < 0])>
                                                {{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-white">{{ number_format($transaction->balance_after) }}</td>
                                            <td class="min-w-64 px-5 py-4 text-silver">{{ $transaction->reason }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.wallet_empty') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </main>
        </div>
    </div>
@endsection
