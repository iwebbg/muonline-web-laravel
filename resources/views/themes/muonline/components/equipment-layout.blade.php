@props(['items'])

<div {{ $attributes->class(['equipment-layout']) }}>
    @for ($slot = 0; $slot < 12; $slot++)
        <div
            class="equipment-slot equipment-slot-{{ $slot }}"
            :style="equipmentSlotStyle({{ $slot }}, itemFor({{ $items }}, {{ $slot }}))"
            aria-hidden="true"
        ></div>
    @endfor

    <template x-for="item in {{ $items }}" :key="`${item.slot}-${item.code}`">
        <button
            type="button"
            class="inventory-item equipment-item"
            :class="[`equipment-slot-${item.slot}`, `inventory-item-${item.rarity}`]"
            :style="equipmentSlotStyle(item.slot, item)"
            :aria-label="item.name"
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
