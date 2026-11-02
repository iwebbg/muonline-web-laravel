@extends('layouts.app')

@section('title', __('ui.jewel_bank.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <x-account-feature-header
            :kicker="__('ui.jewel_bank.kicker')"
            :heading="__('ui.jewel_bank.heading')"
            :intro="__('ui.jewel_bank.intro')"
        />
        <x-account-operation-feedback
            status-session="jewelBankStatus"
            error-key="jewelBank"
            :writes-enabled="$gameWritesEnabled"
            :writes-disabled-message="__('ui.web_storage.writes_disabled')"
        />

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
                    <x-custody-item-card
                        :stored-item="$storedJewel"
                        :action-route="$storedJewel->status === \App\WebStorage\WebStorageItemStatus::Available ? route('account.jewel-bank.destroy', $storedJewel) : null"
                        :action-label="__('ui.jewel_bank.withdraw_one')"
                        :writes-enabled="$gameWritesEnabled"
                    />
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
