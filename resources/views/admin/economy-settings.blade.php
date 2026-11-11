@extends('layouts.admin')

@section('title', __('ui.admin.economy_settings'))

@section('content')
    <div class="admin-shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.economy') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.economy_settings') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.economy_settings_intro') }}</p>
        </div>

        <div class="mt-8">
            <main class="grid min-w-0 content-start gap-6">
                    @if (session('status'))
                        <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('admin.economy-settings.update') }}" class="panel-card p-5 sm:p-7">
                    @csrf
                    @method('PUT')

                    <section>
                        <p class="kicker">{{ __('ui.admin.item_generator_pricing') }}</p>
                        <label class="field-group mt-5 max-w-xl">
                            <span>{{ __('ui.admin.item_generator_markup') }}</span>
                            <input type="number" name="itemGeneratorMarkupPercentage" value="{{ old('itemGeneratorMarkupPercentage', $settings['itemGeneratorMarkupPercentage']) }}" min="0" max="10000" required>
                            <small>{{ __('ui.admin.item_generator_markup_help') }}</small>
                        </label>
                    </section>

                    <section class="mt-8 border-t border-white/8 pt-7">
                        <p class="kicker">{{ __('ui.admin.character_paid_services') }}</p>
                        <div class="mt-5 grid gap-6 md:grid-cols-2">
                            @foreach ([
                                ['characterRenameEnabled', 'characterRenamePriceCredits', 'character_rename', 'character_rename_help'],
                                ['characterClassChangeEnabled', 'characterClassChangePriceCredits', 'character_class_change', 'character_class_change_help'],
                                ['characterResetPurchaseEnabled', 'characterResetPriceCredits', 'character_reset_purchase', 'character_reset_purchase_help'],
                            ] as [$enabledKey, $priceKey, $label, $help])
                                <div class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                    <label class="toggle-control">
                                        <input type="hidden" name="{{ $enabledKey }}" value="0">
                                        <input type="checkbox" name="{{ $enabledKey }}" value="1" @checked(old($enabledKey, $settings[$enabledKey]))>
                                        <span aria-hidden="true"></span><span>{{ __('ui.admin.'.$label) }}</span>
                                    </label>
                                    <label class="field-group mt-4">
                                        <span>{{ __('ui.admin.price_credits') }}</span>
                                        <input type="number" name="{{ $priceKey }}" value="{{ old($priceKey, $settings[$priceKey]) }}" min="0" max="9000000000000000" required>
                                        <small>{{ __('ui.admin.'.$help) }}</small>
                                    </label>
                                </div>
                            @endforeach
                            <div class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                <label class="toggle-control">
                                    <input type="hidden" name="characterLevelUpPointsPurchaseEnabled" value="0">
                                    <input type="checkbox" name="characterLevelUpPointsPurchaseEnabled" value="1" @checked(old('characterLevelUpPointsPurchaseEnabled', $settings['characterLevelUpPointsPurchaseEnabled']))>
                                    <span aria-hidden="true"></span><span>{{ __('ui.admin.character_level_up_points_purchase') }}</span>
                                </label>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <label class="field-group">
                                        <span>{{ __('ui.admin.points_per_package') }}</span>
                                        <input type="number" name="characterLevelUpPointsPackageSize" value="{{ old('characterLevelUpPointsPackageSize', $settings['characterLevelUpPointsPackageSize']) }}" min="1" max="1000000" required>
                                    </label>
                                    <label class="field-group">
                                        <span>{{ __('ui.admin.price_credits') }}</span>
                                        <input type="number" name="characterLevelUpPointsPriceCredits" value="{{ old('characterLevelUpPointsPriceCredits', $settings['characterLevelUpPointsPriceCredits']) }}" min="0" max="9000000000000000" required>
                                    </label>
                                </div>
                                <p class="mt-3 text-xs leading-5 text-steel">{{ __('ui.admin.character_level_up_points_purchase_help') }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8 border-t border-white/8 pt-7">
                        <p class="kicker">{{ __('ui.admin.credit_earning') }}</p>
                        <div class="mt-5 grid gap-6 md:grid-cols-2">
                            <div class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                <label class="toggle-control">
                                    <input type="hidden" name="itemExchangeEnabled" value="0">
                                    <input type="checkbox" name="itemExchangeEnabled" value="1" @checked(old('itemExchangeEnabled', $settings['itemExchangeEnabled']))>
                                    <span aria-hidden="true"></span><span>{{ __('ui.admin.item_exchange') }}</span>
                                </label>
                                <p class="mt-3 text-xs leading-5 text-steel">{{ __('ui.admin.item_exchange_help') }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                <label class="toggle-control">
                                    <input type="hidden" name="onlineTimeRewardsEnabled" value="0">
                                    <input type="checkbox" name="onlineTimeRewardsEnabled" value="1" @checked(old('onlineTimeRewardsEnabled', $settings['onlineTimeRewardsEnabled']))>
                                    <span aria-hidden="true"></span><span>{{ __('ui.admin.online_time_rewards') }}</span>
                                </label>
                                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                    <label class="field-group"><span>{{ __('ui.admin.minutes') }}</span><input type="number" name="onlineMinutesPerUnit" value="{{ old('onlineMinutesPerUnit', $settings['onlineMinutesPerUnit']) }}" min="1" max="1440" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.reward_credits') }}</span><input type="number" name="onlineCreditsPerUnit" value="{{ old('onlineCreditsPerUnit', $settings['onlineCreditsPerUnit']) }}" min="1" max="1000000" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.daily_limit') }}</span><input type="number" name="onlineDailyCreditLimit" value="{{ old('onlineDailyCreditLimit', $settings['onlineDailyCreditLimit']) }}" min="1" max="1000000000" required></label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="mt-8 flex flex-col gap-3 border-t border-white/8 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-steel">{{ __('ui.admin.current_revision', ['revision' => $revision]) }}</p>
                        <button type="submit" class="button button-primary">{{ __('ui.admin.save_and_apply') }}</button>
                    </div>
                    </form>

                    <section
                        class="panel-card p-5 sm:p-7"
                        x-data="{ editingOffer: null }"
                        x-on:keydown.escape.window="editingOffer = null"
                    >
                        <p class="kicker">{{ __('ui.admin.item_exchange_offers') }}</p>
                        <p class="mt-3 text-sm leading-6 text-silver">{{ __('ui.admin.item_exchange_offers_help') }}</p>

                        <form
                            method="POST"
                            action="{{ route('admin.credit-exchange-offers.store') }}"
                            class="mt-6 grid gap-6 rounded-2xl border border-white/8 bg-black/15 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_14rem]"
                            x-data="creditExchangeOfferEditor(@js([
                                'items' => $itemDefinitions,
                                'itemLevels' => $itemLevels,
                                'optionLevels' => $optionLevels,
                                'itemOptionLabels' => $itemOptionLabels,
                                'itemCode' => old('item_code', ''),
                                'level' => old('level', 0),
                                'optionLevel' => old('option_level', 0),
                                'hasSkill' => old('has_skill', false),
                                'hasLuck' => old('has_luck', false),
                                'excellentMask' => old('excellent_mask', 0),
                                'ancientOption' => old('ancient_option', 0),
                                'ui' => [
                                    'skill' => __('ui.admin.item_skill'),
                                    'luck' => __('ui.admin.item_luck'),
                                    'ancient' => __('ui.admin.ancient_option'),
                                    'itemRequired' => __('ui.admin.item_grant_item_required'),
                                ],
                            ]))"
                            x-on:keydown.escape.window="closeItemSuggestions()"
                            x-on:submit="validateSelection($event)"
                        >
                            @csrf
                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="relative md:col-span-2" x-on:click.outside="closeItemSuggestions()">
                                    <input type="hidden" name="item_code" x-bind:value="itemCode">
                                    <label class="field-group">
                                        <span>{{ __('ui.admin.catalog_item') }}</span>
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
                                            aria-controls="credit-exchange-item-suggestions"
                                            x-bind:aria-expanded="itemSuggestionsOpen"
                                            x-bind:aria-activedescendant="activeItemSuggestionIndex >= 0 ? `credit-exchange-item-${activeItemSuggestionIndex}` : ''"
                                            placeholder="{{ __('ui.admin.item_exchange_search_placeholder') }}"
                                            required
                                        >
                                    </label>

                                    <div
                                        id="credit-exchange-item-suggestions"
                                        class="absolute inset-x-0 top-full z-30 mt-2 max-h-80 overflow-y-auto rounded-2xl border border-white/10 bg-[#0d1016]/98 shadow-2xl backdrop-blur-xl"
                                        x-cloak
                                        x-show="itemSuggestionsOpen"
                                        role="listbox"
                                    >
                                        <p x-show="itemSuggestions.length === 0" class="px-4 py-3 text-sm text-steel">{{ __('ui.admin.item_exchange_search_empty') }}</p>
                                        <template x-for="(item, index) in itemSuggestions" :key="item.code">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-4 px-4 py-3 text-left transition hover:bg-white/[0.06]"
                                                x-bind:id="`credit-exchange-item-${index}`"
                                                x-bind:class="activeItemSuggestionIndex === index ? 'bg-white/[0.08]' : ''"
                                                x-on:mouseenter="activeItemSuggestionIndex = index"
                                                x-on:click="selectItem(item)"
                                                role="option"
                                                x-bind:aria-selected="itemCode === item.code"
                                            >
                                                <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25">
                                                    <img x-show="item.image" x-bind:src="item.image" alt="" class="max-h-9 max-w-9 object-contain [image-rendering:pixelated]">
                                                </span>
                                                <span>
                                                    <strong class="block text-sm text-white" x-text="item.name"></strong>
                                                    <small class="text-steel" x-text="item.code"></small>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_level') }}</span>
                                    <select name="level" x-model.number="level" required>
                                        <template x-for="itemLevel in itemLevels" :key="itemLevel">
                                            <option x-bind:value="itemLevel" x-text="itemLevel === 0 ? '0' : `+${itemLevel}`"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.additional_option') }}</span>
                                    <select name="option_level" x-model.number="optionLevel" required>
                                        <template x-for="option in additionalOptionChoices" :key="option.value">
                                            <option x-bind:value="option.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </label>

                                <input type="hidden" name="excellent_mask" x-bind:value="excellentMask">
                                <fieldset class="grid gap-3 md:col-span-2">
                                    <legend class="text-sm font-semibold text-white">{{ __('ui.admin.excellent_options') }}</legend>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <template x-for="option in excellentOptions" :key="option.bit">
                                            <label class="flex items-start gap-3 rounded-xl border border-white/8 bg-black/15 p-3 text-sm text-silver">
                                                <input type="checkbox" x-bind:checked="hasExcellentBit(option.bit)" x-on:change="toggleExcellentBit(option.bit)">
                                                <span x-text="option.label"></span>
                                            </label>
                                        </template>
                                    </div>
                                </fieldset>

                                <label class="flex items-center gap-3 text-sm text-silver">
                                    <input type="hidden" name="has_skill" value="0">
                                    <input type="checkbox" name="has_skill" value="1" x-model="hasSkill">
                                    {{ __('ui.admin.item_skill') }}
                                </label>
                                <label class="flex items-center gap-3 text-sm text-silver">
                                    <input type="hidden" name="has_luck" value="0">
                                    <input type="checkbox" name="has_luck" value="1" x-model="hasLuck">
                                    {{ __('ui.admin.item_luck') }}
                                </label>

                                <label class="field-group md:col-span-2">
                                    <span>{{ __('ui.admin.ancient_option') }}</span>
                                    <input type="number" name="ancient_option" x-model.number="ancientOption" min="0" max="255" required>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.credits_per_item') }}</span>
                                    <input type="number" name="credits_per_item" value="{{ old('credits_per_item', 50) }}" min="1" required>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.max_exchange_quantity') }}</span>
                                    <input type="number" name="max_quantity_per_exchange" value="{{ old('max_quantity_per_exchange', 10) }}" min="1" max="100" required>
                                </label>
                                <label class="field-group md:col-span-2">
                                    <span>{{ __('ui.admin.daily_limit') }}</span>
                                    <input type="number" name="daily_account_credit_limit" value="{{ old('daily_account_credit_limit', 500) }}" min="1" required>
                                </label>
                            </div>

                            <aside class="rounded-2xl border border-white/8 bg-black/20 p-5 text-center">
                                <p class="kicker">{{ __('ui.admin.item_exchange_preview') }}</p>
                                <template x-if="selectedItem">
                                    <div class="mt-5">
                                        <img x-show="selectedItem.image" x-bind:src="selectedItem.image" x-bind:alt="selectedItem.name" class="mx-auto max-h-32 max-w-32 object-contain [image-rendering:pixelated]">
                                        <p class="mt-4 font-semibold text-white" x-text="itemTitle"></p>
                                        <ul x-show="fullItemOptions.length > 0" class="mt-4 grid gap-1 text-left text-xs text-emerald-200">
                                            <template x-for="option in fullItemOptions" :key="option">
                                                <li x-text="option"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </aside>

                            <input type="hidden" name="sort_order" value="0">
                            <input type="hidden" name="enabled" value="1">
                            <div class="lg:col-span-2">
                                <button class="button button-primary">{{ __('ui.admin.create_exchange_offer') }}</button>
                            </div>
                        </form>

                        <div class="mt-6 grid gap-4">
                            @foreach ($exchangeOffers as $presentedOffer)
                                @php($offer = $presentedOffer['model'])
                                <article class="grid gap-5 rounded-2xl border border-white/8 bg-black/15 p-4 lg:grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(7rem,0.65fr))_auto] lg:items-center">
                                    <div class="flex min-w-0 items-center gap-4">
                                        @if ($presentedOffer['item']['image'] !== null)
                                            <span class="grid size-14 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25">
                                                <img src="{{ $presentedOffer['item']['image'] }}" alt="" class="max-h-12 max-w-12 object-contain [image-rendering:pixelated]">
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <h3 class="truncate font-semibold text-white">{{ $presentedOffer['item']['title'] }}</h3>
                                            <p class="mt-1 truncate text-xs text-emerald-200">{{ implode(' · ', $presentedOffer['item']['options']) ?: __('ui.admin.no_item_options') }}</p>
                                            <p class="mt-2 text-xs text-steel">{{ __('ui.admin.current_revision', ['revision' => $offer->revision]) }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="block text-xs text-steel">{{ __('ui.admin.credits_per_item') }}</span>
                                        <strong class="mt-1 block text-sm text-white">{{ number_format($offer->credits_per_item) }}</strong>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-steel">{{ __('ui.admin.max_exchange_quantity') }}</span>
                                        <strong class="mt-1 block text-sm text-white">{{ number_format($offer->max_quantity_per_exchange) }}</strong>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-steel">{{ __('ui.admin.daily_limit') }}</span>
                                        <strong class="mt-1 block text-sm text-white">{{ number_format($offer->daily_account_credit_limit) }}</strong>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-end gap-3">
                                        <span @class([
                                            'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                            'border-emerald-300/25 bg-emerald-300/8 text-emerald-100' => $offer->enabled,
                                            'border-white/10 bg-white/5 text-steel' => ! $offer->enabled,
                                        ])>
                                            {{ $offer->enabled ? __('ui.admin.revision_value_enabled') : __('ui.admin.revision_value_disabled') }}
                                        </span>
                                        <button
                                            type="button"
                                            class="button button-ghost"
                                            x-on:click="editingOffer = @js([
                                                'publicId' => $offer->public_id,
                                                'title' => $presentedOffer['item']['title'],
                                                'options' => implode(' · ', $presentedOffer['item']['options']) ?: __('ui.admin.no_item_options'),
                                                'image' => $presentedOffer['item']['image'],
                                                'revision' => $offer->revision,
                                                'itemCode' => $offer->item_group.':'.$offer->item_index,
                                                'level' => $offer->level,
                                                'optionLevel' => $offer->option_level,
                                                'hasSkill' => (int) $offer->has_skill,
                                                'hasLuck' => (int) $offer->has_luck,
                                                'excellentMask' => $offer->excellent_mask,
                                                'ancientOption' => $offer->ancient_option,
                                                'creditsPerItem' => $offer->credits_per_item,
                                                'maxQuantity' => $offer->max_quantity_per_exchange,
                                                'dailyLimit' => $offer->daily_account_credit_limit,
                                                'enabled' => $offer->enabled,
                                                'sortOrder' => $offer->sort_order,
                                                'updateUrl' => route('admin.credit-exchange-offers.update', $offer),
                                            ])"
                                        >
                                            {{ __('ui.admin.edit_exchange_offer') }}
                                        </button>
                                        <form method="POST" action="{{ route('admin.credit-exchange-offers.destroy', $offer) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-3 py-2 text-sm text-red-300 transition hover:text-red-200">{{ __('ui.admin.archive') }}</button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <template x-teleport="body">
                            <div
                                x-cloak
                                x-show="editingOffer !== null"
                                class="fixed inset-0 z-[120] overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-8"
                                x-on:click.self="editingOffer = null"
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="credit-exchange-offer-editor-title"
                            >
                                <template x-if="editingOffer !== null">
                                    <section class="panel-card mx-auto w-full max-w-2xl p-5 sm:p-8">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="kicker">{{ __('ui.admin.item_exchange_offers') }}</p>
                                            <h2 id="credit-exchange-offer-editor-title" class="mt-2 text-2xl font-semibold text-white">{{ __('ui.admin.edit_exchange_offer') }}</h2>
                                            <p class="mt-2 text-sm leading-6 text-steel">{{ __('ui.admin.edit_exchange_offer_help') }}</p>
                                        </div>
                                        <button type="button" class="icon-button" x-on:click="editingOffer = null" aria-label="{{ __('ui.admin.close_exchange_offer_editor') }}">×</button>
                                    </div>

                                    <div class="mt-6 flex items-center gap-4 rounded-2xl border border-white/8 bg-black/20 p-4">
                                        <span x-show="editingOffer?.image" class="grid size-16 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25">
                                            <img x-bind:src="editingOffer?.image" alt="" class="max-h-14 max-w-14 object-contain [image-rendering:pixelated]">
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="truncate font-semibold text-white" x-text="editingOffer?.title"></h3>
                                            <p class="mt-1 text-xs text-emerald-200" x-text="editingOffer?.options"></p>
                                            <p class="mt-2 text-xs text-steel">
                                                {{ __('ui.admin.revision') }} <span x-text="editingOffer?.revision"></span>
                                            </p>
                                        </div>
                                    </div>

                                    <form method="POST" x-bind:action="editingOffer?.updateUrl ?? ''" class="mt-6 grid gap-5">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="editor_offer_id" x-bind:value="editingOffer?.publicId">
                                        <input type="hidden" name="item_code" x-bind:value="editingOffer?.itemCode">
                                        <input type="hidden" name="level" x-bind:value="editingOffer?.level">
                                        <input type="hidden" name="option_level" x-bind:value="editingOffer?.optionLevel">
                                        <input type="hidden" name="has_skill" x-bind:value="editingOffer?.hasSkill">
                                        <input type="hidden" name="has_luck" x-bind:value="editingOffer?.hasLuck">
                                        <input type="hidden" name="excellent_mask" x-bind:value="editingOffer?.excellentMask">
                                        <input type="hidden" name="ancient_option" x-bind:value="editingOffer?.ancientOption">
                                        <input type="hidden" name="sort_order" x-bind:value="editingOffer?.sortOrder">

                                        <div class="grid gap-5 sm:grid-cols-2">
                                            <label class="field-group">
                                                <span>{{ __('ui.admin.credits_per_item') }}</span>
                                                <input type="number" name="credits_per_item" x-model.number="editingOffer.creditsPerItem" min="1" required>
                                            </label>
                                            <label class="field-group">
                                                <span>{{ __('ui.admin.max_exchange_quantity') }}</span>
                                                <input type="number" name="max_quantity_per_exchange" x-model.number="editingOffer.maxQuantity" min="1" max="100" required>
                                            </label>
                                            <label class="field-group">
                                                <span>{{ __('ui.admin.daily_limit') }}</span>
                                                <input type="number" name="daily_account_credit_limit" x-model.number="editingOffer.dailyLimit" min="1" required>
                                            </label>
                                            <label class="toggle-control self-end pb-3">
                                                <input type="hidden" name="enabled" value="0">
                                                <input type="checkbox" name="enabled" value="1" x-model="editingOffer.enabled">
                                                <span aria-hidden="true"></span>
                                                <span>{{ __('ui.admin.enabled') }}</span>
                                            </label>
                                        </div>

                                        <div class="flex flex-col-reverse gap-3 border-t border-white/8 pt-5 sm:flex-row sm:justify-end">
                                            <button type="button" class="button button-ghost" x-on:click="editingOffer = null">{{ __('ui.admin.cancel') }}</button>
                                            <button type="submit" class="button button-primary">{{ __('ui.admin.save') }}</button>
                                        </div>
                                    </form>
                                    </section>
                                </template>
                            </div>
                        </template>
                    </section>
                <section class="rounded-2xl border border-emerald-300/15 bg-emerald-300/6 p-4 text-sm leading-6 text-emerald-100">
                    <strong class="block text-emerald-200">{{ __('ui.admin.active_configuration') }}</strong>
                    {{ __('ui.admin.active_configuration_help') }}
                </section>

                <x-admin-revision-history
                    :revisions="$recentRevisions"
                    :title="__('ui.admin.revision_history')"
                    :empty-message="__('ui.admin.no_revisions')"
                    wide
                />
            </main>
        </div>
    </div>
@endsection
