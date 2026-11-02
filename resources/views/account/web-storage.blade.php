@extends('layouts.app')

@section('title', __('ui.web_storage.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.web_storage.kicker') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.web_storage.heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.web_storage.intro') }}</p>
            </div>
            <a href="{{ route('account.dashboard') }}" class="button button-ghost">{{ __('ui.web_storage.back_to_account') }}</a>
        </div>

        @if (session('webStorageStatus'))
            <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('webStorageStatus') }}</div>
        @endif
        @if ($errors->has('webStorage'))
            <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first('webStorage') }}</div>
        @endif
        @if (! $gameWritesEnabled)
            <div class="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">{{ __('ui.web_storage.writes_disabled') }}</div>
        @endif

        <section class="mt-8">
            <p class="kicker">{{ __('ui.web_storage.stored_items') }}</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($storedItems as $storedItem)
                    @php($item = $storedItem->item_snapshot)
                    <article class="panel-card flex flex-col p-5">
                        <div class="grid min-h-28 place-items-center rounded-2xl border border-white/8 bg-black/25 p-4">
                            @if (($item['image'] ?? null) !== null)<img src="{{ $item['image'] }}" alt="{{ $item['name'] ?? __('ui.market.unknown_item') }}" class="max-h-24 max-w-full object-contain" loading="lazy">@endif
                        </div>
                        <h2 class="mt-4 font-semibold text-white">{{ $item['name'] ?? __('ui.market.unknown_item') }}{{ ($item['level'] ?? 0) > 0 ? ' +'.$item['level'] : '' }}</h2>
                        <p class="mt-1 text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.web_storage.statuses.'.$storedItem->status->value) }}</p>
                        @if ($storedItem->last_sync_error)<p class="mt-2 text-xs text-red-200">{{ $storedItem->last_sync_error }}</p>@endif
                        @if ($storedItem->status === \App\WebStorage\WebStorageItemStatus::Available)
                            <form method="POST" action="{{ route('account.web-storage.destroy', $storedItem) }}" class="mt-auto pt-5">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button button-primary w-full" @disabled(! $gameWritesEnabled)>{{ __('ui.web_storage.withdraw') }}</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <div class="panel-card p-10 text-center text-steel sm:col-span-2 xl:col-span-4">{{ __('ui.web_storage.empty') }}</div>
                @endforelse
            </div>
        </section>

        <section class="mt-12">
            <p class="kicker">{{ __('ui.web_storage.warehouse_items') }}</p>
            <form method="GET" action="{{ route('account.web-storage.index') }}" class="panel-card mt-5 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label class="field-group flex-1">
                    <span>{{ __('ui.market.character') }}</span>
                    <select name="character" required>
                        @foreach ($characters as $character)<option value="{{ $character->name }}" @selected($selectedCharacter === $character->name)>{{ $character->name }}{{ $character->online ? ' · '.__('ui.market.online') : '' }}</option>@endforeach
                    </select>
                </label>
                <button type="submit" class="button button-ghost">{{ __('ui.market.load_warehouse') }}</button>
            </form>

            <div class="mt-5 grid gap-6">
                @forelse ($warehouseVaults as $vault)
                    <article class="panel-card p-5 sm:p-7">
                        <h2 class="text-xl font-semibold text-white">{{ __('ui.market.vault', ['number' => $vault->number + 1]) }}</h2>
                        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            @forelse ($vault->items as $item)
                                <form method="POST" action="{{ route('account.web-storage.store') }}" class="rounded-2xl border border-white/8 bg-black/20 p-4">
                                    @csrf
                                    <input type="hidden" name="character_name" value="{{ $selectedCharacter }}">
                                    <input type="hidden" name="vault" value="{{ $vault->number }}">
                                    <input type="hidden" name="slot" value="{{ $item->slot }}">
                                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKeys[$vault->number][$item->slot] }}">
                                    <div class="flex min-h-20 items-center gap-4">
                                        @if ($item->image !== null)<img src="{{ $item->image }}" alt="{{ $item->name }}" class="size-16 object-contain" loading="lazy">@endif
                                        <div><strong class="text-white">{{ $item->name }}{{ ($item->level ?? 0) > 0 ? ' +'.$item->level : '' }}</strong><p class="mt-1 text-xs text-steel">{{ $item->code() }}</p></div>
                                    </div>
                                    <button type="submit" class="button button-primary mt-4 w-full" @disabled(! $gameWritesEnabled)>{{ __('ui.web_storage.store') }}</button>
                                </form>
                            @empty
                                <p class="text-sm text-steel">{{ __('ui.market.empty_vault') }}</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="panel-card p-8 text-center text-steel">{{ __('ui.market.no_warehouse') }}</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
