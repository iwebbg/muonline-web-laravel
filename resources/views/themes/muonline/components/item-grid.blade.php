@props([
    'slots',
    'items',
    'useItemSize' => true,
    'itemAction' => null,
    'slotCountExpression' => null,
])

<div {{ $attributes->class(['inventory-grid']) }}>
    @if ($slotCountExpression === null)
        @for ($slot = 0; $slot < $slots; $slot++)
            <div class="inventory-slot" aria-hidden="true"></div>
        @endfor
    @else
        <template x-for="cell in ({{ $slotCountExpression }})" :key="cell">
            <div class="inventory-slot" aria-hidden="true"></div>
        </template>
    @endif

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
            <x-item-image source-expression="item.image" label-expression="item.name" class="inventory-item-image" />
            <x-item-tooltip item="item" />
        </button>
    </template>
</div>
