@extends('layouts.app')

@section('title', __('ui.zen_bank.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.zen_bank.kicker') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.zen_bank.heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.zen_bank.intro') }}</p>
            </div>
            <a href="{{ route('account.dashboard') }}" class="button button-ghost">{{ __('ui.web_storage.back_to_account') }}</a>
        </div>

        @if (session('zenBankStatus'))
            <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                {{ session('zenBankStatus') }}
            </div>
        @endif

        @if ($errors->has('zenBank'))
            <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                {{ $errors->first('zenBank') }}
            </div>
        @endif

        @if (! $gameWritesEnabled)
            <div class="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">
                {{ __('ui.zen_bank.writes_disabled') }}
            </div>
        @endif

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
