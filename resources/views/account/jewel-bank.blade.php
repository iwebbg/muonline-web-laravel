@extends('layouts.app')

@section('title', __('ui.jewel_bank.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.jewel_bank.kicker') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.jewel_bank.heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.jewel_bank.intro') }}</p>
            </div>
            <a href="{{ route('account.dashboard') }}" class="button button-ghost">{{ __('ui.web_storage.back_to_account') }}</a>
        </div>

        @if (session('jewelBankStatus'))
            <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                {{ session('jewelBankStatus') }}
            </div>
        @endif

        @if ($errors->has('jewelBank'))
            <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                {{ $errors->first('jewelBank') }}
            </div>
        @endif

        @if (! $gameWritesEnabled)
            <div class="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">
                {{ __('ui.web_storage.writes_disabled') }}
            </div>
        @endif

        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($jewelDefinitions as $code => $slug)
                <article class="panel-card p-5">
                    <p class="kicker">{{ __('ui.jewel_bank.jewels.'.$slug) }}</p>
                    <strong class="mt-3 block text-3xl text-white">{{ number_format($jewelCounts[$code]) }}</strong>
                    <span class="mt-1 block text-xs text-steel">{{ __('ui.jewel_bank.physical_items') }}</span>
                </article>
            @endforeach
        </section>

        <section class="mt-12">
            <p class="kicker">{{ __('ui.jewel_bank.bank_items') }}</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($storedJewels as $storedJewel)
                    @php($item = $storedJewel->item_snapshot)
                    <article class="panel-card flex flex-col p-5">
                        <div class="grid min-h-24 place-items-center rounded-2xl border border-white/8 bg-black/25 p-4">
                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="max-h-20 max-w-full object-contain" loading="lazy">
                        </div>
                        <h2 class="mt-4 font-semibold text-white">{{ $item['name'] }}</h2>
                        <p class="mt-1 text-xs uppercase tracking-[0.14em] text-steel">
                            {{ __('ui.web_storage.statuses.'.$storedJewel->status->value) }}
                        </p>
                        @if ($storedJewel->status === \App\WebStorage\WebStorageItemStatus::Available)
                            <form method="POST" action="{{ route('account.jewel-bank.destroy', $storedJewel) }}" class="mt-auto pt-5">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button button-primary w-full" @disabled(! $gameWritesEnabled)>
                                    {{ __('ui.jewel_bank.withdraw_one') }}
                                </button>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="panel-card p-10 text-center text-steel sm:col-span-2 xl:col-span-4">
                        {{ __('ui.jewel_bank.empty') }}
                    </div>
                @endforelse
            </div>
        </section>

        <section class="mt-12">
            <p class="kicker">{{ __('ui.jewel_bank.warehouse_jewels') }}</p>
            <form method="GET" action="{{ route('account.jewel-bank.index') }}" class="panel-card mt-5 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label class="field-group flex-1">
                    <span>{{ __('ui.market.character') }}</span>
                    <select name="character" required>
                        @foreach ($characters as $character)
                            <option value="{{ $character->name }}" @selected($selectedCharacter === $character->name)>
                                {{ $character->name }}{{ $character->online ? ' · '.__('ui.market.online') : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="button button-ghost">{{ __('ui.market.load_warehouse') }}</button>
            </form>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($warehouseJewels as $warehouseJewel)
                    @php($item = $warehouseJewel['item'])
                    <form method="POST" action="{{ route('account.jewel-bank.store') }}" class="panel-card p-5">
                        @csrf
                        <input type="hidden" name="character_name" value="{{ $selectedCharacter }}">
                        <input type="hidden" name="vault" value="{{ $warehouseJewel['vault'] }}">
                        <input type="hidden" name="slot" value="{{ $item->slot }}">
                        <input type="hidden" name="idempotency_key" value="{{ $warehouseJewel['idempotencyKey'] }}">
                        <div class="flex min-h-20 items-center gap-4">
                            <img src="{{ $item->image }}" alt="{{ $item->name }}" class="size-16 object-contain" loading="lazy">
                            <div>
                                <strong class="text-white">{{ $item->name }}</strong>
                                <p class="mt-1 text-xs text-steel">{{ __('ui.market.vault', ['number' => $warehouseJewel['vault'] + 1]) }}</p>
                            </div>
                        </div>
                        <button type="submit" class="button button-primary mt-4 w-full" @disabled(! $gameWritesEnabled)>
                            {{ __('ui.jewel_bank.deposit_one') }}
                        </button>
                    </form>
                @empty
                    <div class="panel-card p-8 text-center text-steel sm:col-span-2 xl:col-span-4">
                        {{ __('ui.jewel_bank.no_warehouse_jewels') }}
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
