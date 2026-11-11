@extends('layouts.app')

@section('title', __('ui.zen_bank.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
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

        <section class="mt-8 grid gap-4 md:grid-cols-2">
            <article class="panel-card p-7">
                <p class="kicker">{{ __('ui.zen_bank.bank_balance') }}</p>
                <strong class="mt-3 block text-4xl text-white">{{ number_format($bankBalance) }}</strong>
                <span class="mt-2 block text-sm text-steel">Zen</span>
            </article>
            <article class="panel-card p-7">
                <p class="kicker">{{ __('ui.zen_bank.warehouse_balance') }}</p>
                <strong class="mt-3 block text-4xl text-white">{{ $warehouseBalance === null ? '—' : number_format($warehouseBalance) }}</strong>
                <span class="mt-2 block text-sm text-steel">Zen</span>
            </article>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-2">
            @foreach ([
                ['type' => 'deposit', 'key' => $depositIdempotencyKey],
                ['type' => 'withdrawal', 'key' => $withdrawalIdempotencyKey],
            ] as $transfer)
                <form method="POST" action="{{ route('account.zen-bank.store') }}" class="panel-card p-6">
                    @csrf
                    <input type="hidden" name="type" value="{{ $transfer['type'] }}">
                    <input type="hidden" name="idempotency_key" value="{{ $transfer['key'] }}">
                    <p class="kicker">{{ __('ui.zen_bank.'.$transfer['type']) }}</p>
                    <label class="field-group mt-5">
                        <span>{{ __('ui.market.character') }}</span>
                        <select name="character_name" required>
                            @foreach ($characters as $character)
                                <option value="{{ $character->name }}">
                                    {{ $character->name }}{{ $character->online ? ' · '.__('ui.market.online') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field-group mt-4">
                        <span>{{ __('ui.zen_bank.amount') }}</span>
                        <input name="amount" type="number" min="1" max="2000000000" step="1" required>
                    </label>
                    <button type="submit" class="button button-primary mt-5 w-full" @disabled(! $gameWritesEnabled || $characters === [])>
                        {{ __('ui.zen_bank.confirm_'.$transfer['type']) }}
                    </button>
                </form>
            @endforeach
        </section>

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
