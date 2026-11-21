@extends('layouts.admin')

@section('title', __('ui.s21_warehouse.title'))

@section('content')
    <div class="admin-shell py-10 lg:py-14">
        <h1 class="font-display text-4xl text-white">{{ __('ui.s21_warehouse.title') }}</h1>
        <p class="mt-3 max-w-3xl text-silver">{{ __('ui.s21_warehouse.intro') }}</p>

        <form method="GET" action="{{ route('admin.s21-warehouse') }}" class="panel-card mt-6 flex flex-wrap items-end gap-4 p-5">
            <label class="field-group">
                <span>{{ __('ui.s21_warehouse.account') }}</span>
                <input name="account" value="{{ old('account', $accountId) }}" maxlength="10" required autocomplete="off">
            </label>
            <label class="field-group">
                <span>{{ __('ui.s21_warehouse.vault') }}</span>
                <input name="vault" type="number" min="0" max="2147483647" value="{{ old('vault', $vaultNumber) }}">
            </label>
            <button type="submit" class="button button-primary">{{ __('ui.s21_warehouse.inspect') }}</button>
        </form>
        <p class="mt-2 text-sm text-steel">{{ __('ui.s21_warehouse.vault_help') }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded-xl border border-red-300/25 p-4 text-red-200" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        @if ($storageError !== null)
            <p class="mt-4 text-amber-200" role="status">{{ $storageError }}</p>
        @endif

        @if ($hasStorage)
            <div class="mt-8 grid items-start gap-6 xl:grid-cols-2" x-data="{ selectedSlot: {{ $items[0]['slot'] ?? 'null' }} }">
                <section class="panel-card min-w-0 p-5" aria-label="{{ __('ui.s21_warehouse.grid') }}">
                    <h2 class="font-semibold text-white">{{ __('ui.s21_warehouse.grid') }}</h2>
                    <p class="mt-2 text-sm text-steel">{{ __('ui.s21_warehouse.grid_help') }}</p>
                    <div class="mt-4 overflow-x-auto p-2">
                        <div class="inventory-grid">
                            @for ($slot = 0; $slot < 240; $slot++)
                                <div class="inventory-slot" aria-hidden="true"></div>
                            @endfor
                            @foreach ($items as $item)
                                <button
                                    type="button"
                                    class="inventory-item inventory-grid-item inventory-item-{{ $item['rarity'] }}"
                                    style="{{ $item['style'] }}"
                                    aria-label="{{ $item['title'] }}"
                                    x-bind:aria-pressed="selectedSlot === {{ $item['slot'] }}"
                                    x-on:click="selectedSlot = {{ $item['slot'] }}"
                                    data-s21-slot="{{ $item['slot'] }}"
                                >
                                    <x-item-image :src="$item['image']" :alt="$item['title']" class="inventory-item-image" />
                                    <span class="item-tooltip">
                                        <strong class="item-tooltip-title item-tooltip-title-{{ $item['rarity'] }}">{{ $item['title'] }}</strong>
                                        @foreach ($item['details'] as $detail)
                                            <span class="item-tooltip-line">{{ $detail['label'] }}: {{ $detail['value'] }}</span>
                                        @endforeach
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @if ($items === [])
                        <p class="mt-4 text-silver">{{ __('ui.s21_warehouse.empty_storage') }}</p>
                    @endif
                </section>
                <section class="panel-card min-w-0 p-5" aria-label="{{ __('ui.s21_warehouse.details') }}">
                    <h2 class="font-semibold text-white">{{ __('ui.s21_warehouse.details') }}</h2>
                    <p class="mt-2 text-sm text-steel">{{ __('ui.s21_warehouse.details_help') }}</p>
                    @foreach ($items as $item)
                        <article x-show="selectedSlot === {{ $item['slot'] }}" x-cloak class="mt-5">
                            <div class="flex items-center gap-4">
                                <x-item-image :src="$item['image']" :alt="$item['title']" class="size-16" />
                                <h3 class="font-semibold text-white">{{ $item['title'] }}</h3>
                            </div>
                            <dl class="mt-5 grid gap-3 text-sm">
                                @foreach ($item['details'] as $detail)
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-steel">{{ $detail['label'] }}</dt>
                                        <dd class="text-right text-silver">{{ $detail['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </article>
                    @endforeach
                </section>
            </div>
        @endif
    </div>
@endsection
