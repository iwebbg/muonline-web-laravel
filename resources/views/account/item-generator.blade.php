@extends('layouts.app')
@section('title', __('ui.shop.generator_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.shop.generator_eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.shop.generator_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.shop.generator_intro', ['version' => $currentGameServerVersion]) }}</p>
            </div>
            <div class="panel-card px-5 py-4 text-right">
                <p class="text-xs uppercase tracking-[0.16em] text-steel">{{ __('ui.shop.credits_balance') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gold">{{ number_format($balance) }}</p>
            </div>
        </header>

        <x-account-navigation class="mt-7" />

        <x-shop-navigation active="item-generator" class="mt-6" />

        @if ($errors->any())
            <div class="mt-5 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
        @endif

        <section
            class="panel-card mt-6 p-5 sm:p-7"
            x-data="itemGrantManager(@js([
                'searchUrl' => '',
                'initialAccountId' => '',
                'items' => $itemsByCode,
                'itemLevels' => $itemLevels,
                'optionLevels' => $optionLevels,
                'itemOptionLabels' => $itemOptionLabels,
                'priceModifiers' => $priceModifiers,
                'markupPercentage' => $markupPercentage,
                'initialItemCode' => old('item_code', array_key_first($itemsByCode) ?? ''),
                'initialLevel' => old('level', 0),
                'initialDurability' => old('durability', 255),
                'initialOptionLevel' => old('option_level', 0),
                'initialHasSkill' => old('has_skill', false),
                'initialHasLuck' => old('has_luck', false),
                'initialExcellentMask' => old('excellent_mask', 0),
                'ui' => [
                    'skill' => __('ui.admin.item_grant_skill'),
                    'luck' => __('ui.admin.item_grant_luck'),
                    'itemRequired' => __('ui.admin.item_grant_item_required'),
                ],
            ]))"
            x-on:keydown.escape.window="closeItemSuggestions()"
        >
            <form method="POST" action="{{ route('account.item-generator.purchase') }}" class="grid gap-7" x-on:submit="if (selectedItem === null) { $event.preventDefault(); openConfirmation(); }">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                <input type="hidden" name="item_code" x-bind:value="itemCode">
                <input type="hidden" name="excellent_mask" x-bind:value="excellentMask">
                <input type="hidden" name="ancient_option" value="0">

                <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
                    <div class="grid content-start gap-5 sm:grid-cols-2">
                        <div class="relative sm:col-span-2" x-on:click.outside="closeItemSuggestions()">
                            <label class="field-group">
                                <span>{{ __('ui.shop.generator_search') }}</span>
                                <input
                                    type="search"
                                    x-ref="itemSearch"
                                    x-model="itemSearch"
                                    x-on:input="filterItems()"
                                    x-on:focus="itemSuggestionsOpen = true"
                                    x-on:keydown.down.prevent="moveItemSuggestion(1)"
                                    x-on:keydown.up.prevent="moveItemSuggestion(-1)"
                                    x-on:keydown.enter.prevent="selectActiveItemSuggestion()"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-controls="generator-item-suggestions"
                                    x-bind:aria-expanded="itemSuggestionsOpen"
                                    placeholder="{{ __('ui.admin.item_grant_item_placeholder') }}"
                                    required
                                >
                            </label>
                            <div
                                id="generator-item-suggestions"
                                class="absolute inset-x-0 top-full z-30 mt-2 max-h-80 overflow-y-auto rounded-2xl border border-white/10 bg-[#0d1016]/98 shadow-2xl backdrop-blur-xl"
                                x-cloak
                                x-show="itemSuggestionsOpen"
                                role="listbox"
                            >
                                <p x-show="itemSuggestions.length === 0" class="px-4 py-3 text-sm text-steel">{{ __('ui.admin.item_grant_item_empty') }}</p>
                                <template x-for="(item, index) in itemSuggestions" :key="item.code">
                                    <button type="button" class="flex w-full items-center gap-4 px-4 py-3 text-left transition hover:bg-white/[0.06]" x-on:click="selectItem(item)" role="option">
                                        <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25">
                                            <img x-show="item.image" x-bind:src="item.image" alt="" class="max-h-9 max-w-9 object-contain [image-rendering:pixelated]">
                                        </span>
                                        <span>
                                            <strong class="block text-sm text-white" x-text="item.name"></strong>
                                            <small class="text-steel" x-text="`${item.basePrice} Credits`"></small>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <label class="field-group">
                            <span>{{ __('ui.admin.item_grant_level') }}</span>
                            <select name="level" x-model.number="level" required>
                                <template x-for="itemLevel in availableItemLevels" :key="itemLevel">
                                    <option x-bind:value="itemLevel" x-text="itemLevel === 0 ? '0' : `+${itemLevel}`"></option>
                                </template>
                            </select>
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.item_grant_durability') }}</span>
                            <input type="number" name="durability" x-model.number="durability" min="0" max="255" required>
                        </label>
                        <div class="grid gap-3 sm:col-span-2 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-end">
                            <label class="field-group">
                                <span>{{ __('ui.admin.item_grant_option_level') }}</span>
                                <select name="option_level" x-model.number="optionLevel" required>
                                    <template x-for="option in additionalOptionChoices" :key="option.value">
                                        <option x-bind:value="option.value" x-text="option.label"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="flex min-h-11 items-center gap-3 rounded-xl border border-white/8 px-4 py-3 text-sm text-silver">
                                <input type="checkbox" name="has_skill" value="1" x-model="hasSkill" x-bind:disabled="selectedItem?.allowsSkill === false">
                                {{ __('ui.admin.item_grant_skill') }}
                            </label>
                            <label class="flex min-h-11 items-center gap-3 rounded-xl border border-white/8 px-4 py-3 text-sm text-silver">
                                <input type="checkbox" name="has_luck" value="1" x-model="hasLuck" x-bind:disabled="selectedItem?.allowsLuck === false">
                                {{ __('ui.admin.item_grant_luck') }}
                            </label>
                        </div>

                        <fieldset class="grid gap-3 sm:col-span-2">
                            <legend class="text-sm font-semibold text-white">{{ __('ui.admin.item_grant_excellent_options') }}</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <template x-for="option in excellentOptions" :key="option.bit">
                                    <label class="flex items-start gap-3 rounded-xl border border-white/8 bg-black/15 p-3 text-sm text-silver">
                                        <input type="checkbox" x-model.number="excellentOptionBits" x-bind:value="option.bit" x-bind:disabled="excellentOptionBits.length >= selectedItem?.maxExcellent && !excellentOptionBits.includes(option.bit)">
                                        <span x-text="option.label"></span>
                                    </label>
                                </template>
                            </div>
                        </fieldset>
                    </div>

                    <aside class="self-start rounded-2xl border border-white/8 bg-black/20 p-5 text-center">
                        <p class="kicker">{{ __('ui.admin.item_grant_preview') }}</p>
                        <template x-if="selectedItem">
                            <div class="mt-5">
                                <img x-show="selectedItem.image" :src="selectedItem.image" :alt="selectedItem.name" class="mx-auto max-h-32 max-w-32 object-contain [image-rendering:pixelated]">
                                <p class="mt-4 font-semibold text-white" x-text="itemTitle"></p>
                                <ul x-show="fullItemOptions.length > 0" class="mt-4 grid gap-1 text-left text-xs text-emerald-200">
                                    <template x-for="option in fullItemOptions" :key="option"><li x-text="option"></li></template>
                                </ul>
                                <div class="mt-6 border-t border-white/8 pt-5">
                                    <span class="text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.shop.price') }}</span>
                                    <strong class="mt-1 block text-2xl text-gold" x-text="`${calculatedPrice.toLocaleString()} Credits`"></strong>
                                </div>
                            </div>
                        </template>
                    </aside>
                </div>

                <div class="flex flex-col gap-4 border-t border-white/8 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-2xl text-sm text-steel">{{ __('ui.shop.generator_price_help') }}</p>
                    <button type="submit" class="button button-primary">{{ __('ui.shop.generator_purchase') }}</button>
                </div>
            </form>
        </section>
    </div>
@endsection
