@extends('layouts.app')

@section('title', __('ui.admin.economy_settings'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.economy') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.economy_settings') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.economy_settings_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="economy-settings" />

            <main class="grid min-w-0 content-start gap-6 2xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="grid content-start gap-6">
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

                    <section class="panel-card p-5 sm:p-7">
                        <p class="kicker">{{ __('ui.admin.item_exchange_offers') }}</p>
                        <p class="mt-3 text-sm leading-6 text-silver">{{ __('ui.admin.item_exchange_offers_help') }}</p>

                        <form method="POST" action="{{ route('admin.credit-exchange-offers.store') }}" class="mt-6 grid gap-4 rounded-2xl border border-white/8 bg-black/15 p-4 md:grid-cols-3" x-data="creditExchangeOfferEditor(@js(['items' => $itemDefinitions, 'itemOptionLabels' => $itemOptionLabels, 'itemCode' => old('item_code', ''), 'excellentMask' => old('excellent_mask', 0)]))">
                            @csrf
                            <label class="field-group md:col-span-3"><span>{{ __('ui.admin.catalog_item') }}</span><select name="item_code" x-model="itemCode" @change="clearExcellentOptions()" required><option value="">{{ __('ui.admin.choose_item') }}</option>@foreach ($itemDefinitions as $item)<option value="{{ $item['code'] }}">{{ $item['name'] }} ({{ $item['code'] }})</option>@endforeach</select></label>
                            <label class="field-group"><span>{{ __('ui.admin.item_level') }}</span><select name="level">@foreach ($itemLevels as $level)<option value="{{ $level }}" @selected((int) old('level', 0) === $level)>+{{ $level }}</option>@endforeach</select></label>
                            <label class="field-group"><span>{{ __('ui.admin.additional_option') }}</span><select name="option_level">@foreach ($optionLevels as $optionLevel)<option value="{{ $optionLevel }}" @selected((int) old('option_level', 0) === $optionLevel)>{{ $optionLevel }}</option>@endforeach</select></label>
                            <fieldset class="field-group"><legend>{{ __('ui.admin.excellent_options') }}</legend><input type="hidden" name="excellent_mask" :value="excellentMask"><div class="mt-2 grid gap-2"><template x-for="(label, index) in excellentOptions" :key="index"><label x-show="label" class="flex items-start gap-2 text-xs text-silver"><input type="checkbox" :checked="hasExcellentBit(index)" @change="toggleExcellentBit(index)"><span x-text="label"></span></label></template></div><small>{{ __('ui.admin.excellent_mask_help') }}</small></fieldset>
                            <label class="toggle-control"><input type="hidden" name="has_skill" value="0"><input type="checkbox" name="has_skill" value="1" @checked(old('has_skill'))><span aria-hidden="true"></span><span>{{ __('ui.admin.item_skill') }}</span></label>
                            <label class="toggle-control"><input type="hidden" name="has_luck" value="0"><input type="checkbox" name="has_luck" value="1" @checked(old('has_luck'))><span aria-hidden="true"></span><span>{{ __('ui.admin.item_luck') }}</span></label>
                            <label class="field-group"><span>{{ __('ui.admin.ancient_option') }}</span><input type="number" name="ancient_option" value="{{ old('ancient_option', 0) }}" min="0" max="255" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.credits_per_item') }}</span><input type="number" name="credits_per_item" value="{{ old('credits_per_item', 50) }}" min="1" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.max_exchange_quantity') }}</span><input type="number" name="max_quantity_per_exchange" value="{{ old('max_quantity_per_exchange', 10) }}" min="1" max="100" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.daily_limit') }}</span><input type="number" name="daily_account_credit_limit" value="{{ old('daily_account_credit_limit', 500) }}" min="1" required></label>
                            <input type="hidden" name="sort_order" value="0"><input type="hidden" name="enabled" value="1">
                            <div class="md:col-span-3"><button class="button button-primary">{{ __('ui.admin.create_exchange_offer') }}</button></div>
                        </form>

                        <div class="mt-6 grid gap-4">
                            @foreach ($exchangeOffers as $presentedOffer)
                                @php($offer = $presentedOffer['model'])
                                <article class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div><h3 class="font-semibold text-white">{{ $presentedOffer['item']['title'] }}</h3><p class="mt-1 text-xs text-emerald-200">{{ implode(' · ', $presentedOffer['item']['options']) ?: __('ui.admin.no_item_options') }}</p><p class="mt-2 text-xs text-steel">{{ __('ui.admin.current_revision', ['revision' => $offer->revision]) }}</p></div>
                                        <form method="POST" action="{{ route('admin.credit-exchange-offers.update', $offer) }}" class="grid flex-1 gap-3 sm:grid-cols-4 lg:max-w-3xl">@csrf @method('PUT')
                                            @foreach (['item_code' => $offer->item_group.':'.$offer->item_index, 'level' => $offer->level, 'option_level' => $offer->option_level, 'has_skill' => (int) $offer->has_skill, 'has_luck' => (int) $offer->has_luck, 'excellent_mask' => $offer->excellent_mask, 'ancient_option' => $offer->ancient_option, 'sort_order' => $offer->sort_order] as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                                            <label class="field-group"><span>{{ __('ui.admin.credits_per_item') }}</span><input type="number" name="credits_per_item" value="{{ $offer->credits_per_item }}" min="1" required></label>
                                            <label class="field-group"><span>{{ __('ui.admin.max_exchange_quantity') }}</span><input type="number" name="max_quantity_per_exchange" value="{{ $offer->max_quantity_per_exchange }}" min="1" max="100" required></label>
                                            <label class="field-group"><span>{{ __('ui.admin.daily_limit') }}</span><input type="number" name="daily_account_credit_limit" value="{{ $offer->daily_account_credit_limit }}" min="1" required></label>
                                            <div class="flex items-end gap-3"><label class="toggle-control"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($offer->enabled)><span aria-hidden="true"></span><span>{{ __('ui.admin.enabled') }}</span></label><button class="button button-ghost">{{ __('ui.admin.save') }}</button></div>
                                        </form>
                                        <form method="POST" action="{{ route('admin.credit-exchange-offers.destroy', $offer) }}">@csrf @method('DELETE')<button class="text-sm text-red-300">{{ __('ui.admin.archive') }}</button></form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </div>

                <aside class="grid content-start gap-4">
                    <section class="rounded-2xl border border-emerald-300/15 bg-emerald-300/6 p-4 text-sm leading-6 text-emerald-100">
                        <strong class="block text-emerald-200">{{ __('ui.admin.active_configuration') }}</strong>
                        {{ __('ui.admin.active_configuration_help') }}
                    </section>

                    <x-admin-revision-history
                        :revisions="$recentRevisions"
                        :title="__('ui.admin.revision_history')"
                        :empty-message="__('ui.admin.no_revisions')"
                    />
                </aside>
            </main>
        </div>
    </div>
@endsection
