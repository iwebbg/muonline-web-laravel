@extends('layouts.app')

@section('title', __('ui.web_storage.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <x-account-feature-header
            :kicker="__('ui.web_storage.kicker')"
            :heading="__('ui.web_storage.heading')"
            :intro="__('ui.web_storage.intro')"
        />
        <x-account-operation-feedback
            status-session="webStorageStatus"
            error-key="webStorage"
            :writes-enabled="$gameWritesEnabled"
            :writes-disabled-message="__('ui.web_storage.writes_disabled')"
        />

        <section class="mt-8">
            <p class="kicker">{{ __('ui.web_storage.stored_items') }}</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($storedItems as $storedItem)
                    <x-custody-item-card
                        :stored-item="$storedItem"
                        :action-route="$storedItem->status === \App\WebStorage\WebStorageItemStatus::Available ? route('account.web-storage.destroy', $storedItem) : null"
                        :action-label="__('ui.web_storage.withdraw')"
                        :writes-enabled="$gameWritesEnabled"
                    />
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
