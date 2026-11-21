@extends('layouts.app')

@section('title', __('ui.zen_bank.title'))

@section('content')
    <div class="shell py-10 lg:py-14" x-data="zenBankPanel(@js(old('type')))">
        <x-account-feature-header
            :kicker="__('ui.zen_bank.kicker')"
            :heading="__('ui.zen_bank.heading')"
            :intro="__('ui.zen_bank.intro')"
            :show-back="false"
        />
        <x-account-navigation class="mt-6" />
        <x-account-operation-feedback
            status-session="zenBankStatus"
            error-key="zenBank"
            :writes-enabled="$gameWritesEnabled"
            :writes-disabled-message="__('ui.zen_bank.writes_disabled')"
        />

        <section class="panel-card mt-8 p-6 sm:p-7">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="kicker">{{ __('ui.zen_bank.bank_balance') }}</p>
                    <p class="mt-3 flex items-baseline gap-3">
                        <strong class="text-4xl tabular-nums text-white">{{ number_format($bankBalance) }}</strong>
                        <span class="text-sm text-steel">Zen</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach (['deposit', 'withdrawal'] as $type)
                        <button
                            type="button"
                            @class(['button', 'button-primary' => $type === 'deposit', 'button-ghost' => $type === 'withdrawal'])
                            x-on:click="open('{{ $type }}', $el)"
                            aria-haspopup="dialog"
                            aria-controls="zen-{{ $type }}-dialog"
                            @disabled(! $gameWritesEnabled || $characters === [])
                        >{{ __('ui.zen_bank.confirm_'.$type) }}</button>
                    @endforeach
                </div>
            </div>
            <p class="mt-5 border-t border-white/8 pt-4 text-sm text-steel">{{ __('ui.zen_bank.transfer_help') }}</p>
        </section>

        @foreach ([
            ['type' => 'deposit', 'key' => $depositIdempotencyKey],
            ['type' => 'withdrawal', 'key' => $withdrawalIdempotencyKey],
        ] as $transfer)
            @php
                $availableBalance = $transfer['type'] === 'deposit' ? $warehouseBalance : $bankBalance;
                $formattedAvailableBalance = $availableBalance === null ? '—' : number_format($availableBalance);
                $restoringTransfer = old('type') === $transfer['type'];
            @endphp
            <template x-teleport="body">
                <div
                    class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-6"
                    x-show="activeTransfer === '{{ $transfer['type'] }}'"
                    x-transition.opacity
                    x-cloak
                    x-on:click.self="close()"
                    x-on:keydown.escape.window="close()"
                    x-on:keydown.tab="trapFocus($event)"
                >
                    <section
                        id="zen-{{ $transfer['type'] }}-dialog"
                        x-ref="{{ $transfer['type'] }}Panel"
                        class="panel-card my-auto w-full max-w-xl shadow-2xl shadow-black/60"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="zen-{{ $transfer['type'] }}-title"
                    >
                        <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                            <div>
                                <p class="kicker">{{ __('ui.zen_bank.heading') }}</p>
                                <h2 id="zen-{{ $transfer['type'] }}-title" class="mt-2 text-2xl font-semibold text-white">{{ __('ui.zen_bank.confirm_'.$transfer['type']) }}</h2>
                            </div>
                            <button type="button" class="icon-button shrink-0" aria-label="{{ __('ui.zen_bank.close') }}" x-on:click="close()">×</button>
                        </div>

                        <p class="mt-5 rounded-xl border border-gold/20 bg-gold/[0.06] px-4 py-3 text-sm font-semibold text-gold">{{ __('ui.zen_bank.direction_'.$transfer['type']) }}</p>
                        <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-steel">{{ __('ui.zen_bank.warehouse_balance') }}</dt>
                                <dd class="mt-1 font-semibold tabular-nums text-white">{{ $warehouseBalance === null ? '—' : number_format($warehouseBalance) }} Zen</dd>
                            </div>
                            <div class="text-right">
                                <dt class="text-steel">{{ __('ui.zen_bank.bank_balance') }}</dt>
                                <dd class="mt-1 font-semibold tabular-nums text-white">{{ number_format($bankBalance) }} Zen</dd>
                            </div>
                        </dl>

                        <form method="POST" action="{{ route('account.zen-bank.store') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="type" value="{{ $transfer['type'] }}">
                            <input type="hidden" name="idempotency_key" value="{{ $transfer['key'] }}">
                            <label class="field-group">
                                <span>{{ __('ui.market.character') }}</span>
                                <select name="character_name" required>
                                    @foreach ($characters as $character)
                                        <option value="{{ $character->name }}" @selected($restoringTransfer && old('character_name') === $character->name)>
                                            {{ $character->name }} ({{ number_format($character->zen) }} Zen){{ $character->online ? ' · '.__('ui.market.online') : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <small>{{ __('ui.zen_bank.character_balance_help') }}</small>
                            </label>
                            @if ($restoringTransfer)
                                @error('character_name')
                                    <p class="mt-2 text-sm text-red-200" role="alert">{{ $message }}</p>
                                @enderror
                            @endif
                            <label class="field-group mt-4">
                                <span>
                                    {{ __('ui.zen_bank.amount') }}
                                    <span class="font-normal text-steel">({{ __('ui.zen_bank.available_amount', ['amount' => $formattedAvailableBalance]) }})</span>
                                </span>
                                <input name="amount" type="number" min="1" max="2000000000" step="1" value="{{ $restoringTransfer ? old('amount') : '' }}" required>
                            </label>
                            @if ($restoringTransfer)
                                @error('amount')
                                    <p class="mt-2 text-sm text-red-200" role="alert">{{ $message }}</p>
                                @enderror
                            @endif
                            <p class="mt-4 text-xs text-steel">{{ __('ui.zen_bank.offline_notice') }}</p>
                            <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-white/8 pt-5">
                                <button type="button" class="button button-ghost" x-on:click="close()">{{ __('ui.account.reset_cancel') }}</button>
                                <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled || $characters === [])>
                                    {{ __('ui.zen_bank.confirm_'.$transfer['type']) }}
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </template>
        @endforeach

        <section class="mt-12">
            <p class="kicker">{{ __('ui.zen_bank.history') }}</p>
            <div class="panel-card mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/8 text-steel">
                        <tr>
                            <th class="p-4">{{ __('ui.zen_bank.transfer') }}</th>
                            <th class="p-4">{{ __('ui.zen_bank.amount') }}</th>
                            <th class="p-4">{{ __('ui.zen_bank.status') }}</th>
                            <th class="p-4">{{ __('ui.zen_bank.created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($operations as $operation)
                            <tr class="border-b border-white/5 last:border-0">
                                <td class="p-4 text-white">{{ __('ui.zen_bank.'.$operation->type->value) }}</td>
                                <td class="p-4 text-white">{{ number_format($operation->amount) }}</td>
                                <td class="p-4 text-steel">{{ __('ui.zen_bank.statuses.'.$operation->status->value) }}</td>
                                <td class="p-4 text-steel">{{ $operation->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-steel">{{ __('ui.zen_bank.no_history') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
