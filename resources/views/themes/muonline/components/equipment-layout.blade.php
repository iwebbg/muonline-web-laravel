@props(['items', 'slotNumbersExpression' => null])

<div {{ $attributes->class(['equipment-layout']) }}
    @if ($slotNumbersExpression !== null)
        :style="({{ $slotNumbersExpression }}) !== null ? 'grid-template-rows: repeat(10, var(--equipment-cell-size))' : ''"
    @endif
>
    @if ($slotNumbersExpression === null)
    @for ($slot = 0; $slot < 12; $slot++)
        <div
            class="equipment-slot equipment-slot-{{ $slot }}"
            :style="equipmentSlotStyle({{ $slot }}, itemFor({{ $items }}, {{ $slot }}))"
            aria-hidden="true"
        ></div>
    @endfor
    @else
        <template x-for="slot in ({{ $slotNumbersExpression }} ?? [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11])" :key="slot">
            <div class="equipment-slot" :style="equipmentSlotStyle(slot, itemFor({{ $items }}, slot))" aria-hidden="true"></div>
        </template>
    @endif

    <template x-for="item in {{ $items }}" :key="`${item.slot}-${item.code}`">
        <button
            type="button"
            class="inventory-item equipment-item"
            :class="{ [`equipment-slot-${item.slot}`]: true, [`inventory-item-${item.rarity}`]: true, 'inventory-item-compact': (String(item.code).startsWith('7:') || String(item.code).startsWith('10:')) || (Number(item.width) === 1 && Number(item.height) === 1) }"
            :style="equipmentSlotStyle(item.slot, item)"
            :aria-label="item.name"
        >
            <x-item-image source-expression="item.image" label-expression="item.name" class="inventory-item-image" />
            <x-item-tooltip item="item" />
        </button>
    </template>
</div>
