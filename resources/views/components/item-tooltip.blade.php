@props(['item'])

<span class="item-tooltip">
    <strong
        class="item-tooltip-title"
        :class="'item-tooltip-title-' + itemNameTone({{ $item }})"
        x-text="itemTitle({{ $item }})"
    ></strong>
    <span
        class="item-tooltip-line"
        x-text="itemDurabilityText({{ $item }})"
    ></span>
    <span
        class="item-tooltip-line item-tooltip-option"
        x-show="{{ $item }}.hasSkill"
        x-text="itemOptionLabels.skill"
    ></span>
    <template x-if="{{ $item }}.hasLuck">
        <span>
            <span
                class="item-tooltip-line item-tooltip-option"
                x-text="itemOptionLabels.luckSoul"
            ></span>
            <span
                class="item-tooltip-line item-tooltip-option"
                x-text="itemOptionLabels.luckCritical"
            ></span>
        </span>
    </template>
    <template x-if="itemAdditionalOption({{ $item }}) !== null">
        <span
            class="item-tooltip-line item-tooltip-option"
            x-text="itemAdditionalOption({{ $item }})"
        ></span>
    </template>
    <template x-for="option in itemExcellentOptions({{ $item }})" :key="option">
        <span class="item-tooltip-line item-tooltip-excellent" x-text="option"></span>
    </template>
    <span
        class="item-tooltip-line item-tooltip-ancient"
        x-show="{{ $item }}.ancientOption > 0"
        x-text="itemAncientOption({{ $item }})"
    ></span>
</span>
