@props([
    'slots',
    'items',
    'useItemSize' => true,
    'itemAction' => null,
])

<div {{ $attributes->class(['inventory-grid']) }}>
    @for ($slot = 0; $slot < $slots; $slot++)
        <div class="inventory-slot" aria-hidden="true"></div>
    @endfor

    <template x-for="item in {{ $items }}" :key="`${item.slot}-${item.code}`">
        <button
            type="button"
            class="inventory-item inventory-grid-item"
            :class="`inventory-item-${item.rarity}`"
            :style="itemGridStyle(item, 8, {{ $useItemSize ? 'true' : 'false' }})"
            :aria-label="item.name"
            @if ($itemAction !== null)
                @click="{{ $itemAction }}(item)"
            @endif
        >
            <template x-if="item.image !== null">
                <img
                    :src="item.image"
                    :alt="item.name"
                    class="inventory-item-image"
                    loading="lazy"
                >
            </template>
            <span x-show="item.image === null" x-text="item.code"></span>
            <x-item-tooltip item="item" />
        </button>
    </template>
</div>
