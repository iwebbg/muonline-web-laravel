@props(['definition', 'state', 'update', 'level', 'namePrefix' => null])

<div
    x-data="itemAdvancedOptions(() => ({{ $definition }}), () => ({{ $state }}), (field, value) => {{ $update }}, () => ({{ $level }}), @js(__('ui.shop.generator_harmony_options')))"
    class="md:col-span-full"
>
    @foreach (['ancient_option', 'harmony_option', 'socket_count', 'has_380_option'] as $field)
        <input
            type="hidden"
            @if ($namePrefix !== null)
                x-bind:name="{{ $namePrefix }} + '[{{ $field }}]'"
            @else
                name="{{ $field }}"
            @endif
            x-bind:value="{{ $field === 'has_380_option' ? '(state.has_380_option ? 1 : 0)' : 'state.'.$field.' ?? 0' }}"
        >
    @endforeach

    <fieldset class="mt-5 grid gap-4 rounded-xl border border-gold/15 p-4 sm:grid-cols-2" x-show="visible" x-cloak>
        <legend class="px-2 text-sm font-semibold text-gold">{{ __('ui.admin.item_grant_advanced_options') }}</legend>
        <label class="field-group" x-show="(definition.ancientOptions?.length ?? 0) > 0">
            <span>{{ __('ui.shop.generator_ancient') }}</span>
            <select x-on:change="update('ancient_option', Number($el.value))" x-effect="$nextTick(() => $el.value = String(state.ancient_option ?? 0))">
                <option value="0">{{ __('ui.shop.generator_ancient_none') }}</option>
                <template x-for="value in definition.ancientOptions ?? []" :key="value">
                    <option x-bind:value="value" x-text="ancientLabel(value)"></option>
                </template>
            </select>
        </label>
        <label class="field-group" x-show="(definition.harmonyOptions?.length ?? 0) > 0">
            <span>{{ __('ui.shop.generator_harmony') }}</span>
            <select x-on:change="update('harmony_option', Number($el.value))" x-effect="$nextTick(() => $el.value = String(state.harmony_option ?? 0))">
                <option value="0">{{ __('ui.shop.generator_harmony_none') }}</option>
                <template x-for="option in harmonyChoices" :key="option.value">
                    <option x-bind:value="option.value" x-text="option.label"></option>
                </template>
            </select>
            <small x-show="harmonyChoices.length === 0">{{ __('ui.admin.item_grant_harmony_level_help') }}</small>
        </label>
        <label class="field-group" x-show="definition.maxSockets > 0">
            <span>{{ __('ui.shop.generator_sockets') }}</span>
            <select x-on:change="update('socket_count', Number($el.value))" x-effect="$nextTick(() => $el.value = String(state.socket_count ?? 0))">
                <option value="0">0</option>
                <template x-for="count in definition.maxSockets ?? 0" :key="count">
                    <option x-bind:value="count" x-text="count"></option>
                </template>
            </select>
        </label>
        <label class="flex items-center gap-3 text-sm text-silver" x-show="definition.allows380">
            <input type="checkbox" x-bind:checked="state.has_380_option" x-on:change="update('has_380_option', $el.checked)">
            {{ __('ui.shop.generator_380') }}
        </label>
    </fieldset>
</div>
