@props([
    'storedItem',
    'actionRoute' => null,
    'actionLabel' => null,
    'writesEnabled' => false,
])

@php($itemSnapshot = $storedItem->item_snapshot)

<article {{ $attributes->class('panel-card flex flex-col p-5') }}>
    <div class="grid min-h-28 place-items-center rounded-2xl border border-white/8 bg-black/25 p-4">
        @if (($itemSnapshot['image'] ?? null) !== null)
            <img
                src="{{ $itemSnapshot['image'] }}"
                alt="{{ $itemSnapshot['name'] ?? __('ui.market.unknown_item') }}"
                class="max-h-24 max-w-full object-contain"
                loading="lazy"
            >
        @endif
    </div>
    <h2 class="mt-4 font-semibold text-white">
        {{ $itemSnapshot['name'] ?? __('ui.market.unknown_item') }}{{ ($itemSnapshot['level'] ?? 0) > 0 ? ' +'.$itemSnapshot['level'] : '' }}
    </h2>
    <p class="mt-1 text-xs uppercase tracking-[0.14em] text-steel">
        {{ __('ui.web_storage.statuses.'.$storedItem->status->value) }}
    </p>
    @if ($storedItem->last_sync_error)
        <p class="mt-2 text-xs text-red-200">{{ $storedItem->last_sync_error }}</p>
    @endif
    @if ($actionRoute !== null && $actionLabel !== null)
        <form method="POST" action="{{ $actionRoute }}" class="mt-auto pt-5">
            @csrf
            @method('DELETE')
            <button type="submit" class="button button-primary w-full" @disabled(! $writesEnabled)>
                {{ $actionLabel }}
            </button>
        </form>
    @endif
</article>
