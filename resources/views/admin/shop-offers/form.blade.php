@extends('layouts.app')

@section('title', $offer ? __('ui.admin.shop_offer_edit') : __('ui.admin.shop_offer_create'))

@section('content')
    @php
        $initialItems = old('items', $offer?->items->map(fn ($item) => [
            'item_code' => $item->item_code,
            'level' => $item->level,
            'durability' => $item->durability,
            'option_level' => $item->option_level,
            'has_skill' => $item->has_skill,
            'has_luck' => $item->has_luck,
            'excellent_mask' => $item->excellent_mask,
            'ancient_option' => $item->ancient_option,
            'weight' => $item->weight,
        ])->values()->all() ?? []);
    @endphp
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.shop_offers_heading') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ $offer ? __('ui.admin.shop_offer_edit') : __('ui.admin.shop_offer_create') }}</h1>
        </div>
        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="shop-offers" />
            <main class="min-w-0">
                @if (session('status'))<div class="mb-5 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100">{{ session('status') }}</div>@endif
                @if ($errors->any())<div class="mb-5 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100">{{ $errors->first() }}</div>@endif
                <form method="POST" action="{{ $offer ? route('admin.shop-offers.update', $offer) : route('admin.shop-offers.store') }}" class="panel-card grid gap-6 p-5 sm:p-7" x-data="shopOfferEditor(@js($initialItems))">
                    @csrf
                    @if ($offer) @method('PUT') @endif
                    <div class="grid gap-5 md:grid-cols-3">
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_type') }}</span><select name="type">@foreach ($types as $type)<option value="{{ $type->value }}" @selected(old('type', $offer?->type->value ?? 'item') === $type->value)>{{ __('ui.admin.shop_offer_types.'.$type->value) }}</option>@endforeach</select></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_class_scope') }}</span><select name="class_scope">@foreach ($classScopes as $scope)<option value="{{ $scope->value }}" @selected(old('class_scope', $offer?->class_scope->value ?? 'all') === $scope->value)>{{ __('ui.shop.class_scopes.'.$scope->value) }}</option>@endforeach</select></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_status') }}</span><select name="status">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $offer?->status->value ?? 'draft') === $status->value)>{{ __('ui.admin.shop_offer_statuses.'.$status->value) }}</option>@endforeach</select></label>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_title_en') }}</span><input name="title_en" value="{{ old('title_en', $offer?->title_en) }}" maxlength="120" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_title_bg') }}</span><input name="title_bg" value="{{ old('title_bg', $offer?->title_bg) }}" maxlength="120" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_description_en') }}</span><textarea name="description_en" rows="3" maxlength="2000">{{ old('description_en', $offer?->description_en) }}</textarea></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_description_bg') }}</span><textarea name="description_bg" rows="3" maxlength="2000">{{ old('description_bg', $offer?->description_bg) }}</textarea></label>
                    </div>
                    <div class="grid gap-5 md:grid-cols-4">
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_price') }}</span><input type="number" name="price_credits" value="{{ old('price_credits', $offer?->price_credits ?? 100) }}" min="1" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_sort_order') }}</span><input type="number" name="sort_order" value="{{ old('sort_order', $offer?->sort_order ?? 0) }}" min="0" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_available_from') }}</span><input type="datetime-local" name="available_from" value="{{ old('available_from', $offer?->available_from?->format('Y-m-d\TH:i')) }}"></label>
                        <label class="field-group"><span>{{ __('ui.admin.shop_offer_available_until') }}</span><input type="datetime-local" name="available_until" value="{{ old('available_until', $offer?->available_until?->format('Y-m-d\TH:i')) }}"></label>
                    </div>

                    <fieldset class="grid gap-4 border-t border-white/8 pt-6">
                        <div class="flex items-center justify-between gap-4"><legend class="kicker">{{ __('ui.admin.shop_offer_items') }}</legend><button type="button" class="button button-ghost" @click="addItem()">{{ __('ui.admin.shop_offer_add_item') }}</button></div>
                        <datalist id="shop-item-catalog">@foreach ($definitions as $definition)<option value="{{ $definition['code'] }}">{{ $definition['name'] }}</option>@endforeach</datalist>
                        <template x-for="(item, index) in items" :key="index">
                            <div class="rounded-2xl border border-white/8 bg-black/20 p-4">
                                <div class="grid gap-4 md:grid-cols-4">
                                    <label class="field-group md:col-span-2"><span>{{ __('ui.admin.shop_offer_item') }}</span><input list="shop-item-catalog" x-bind:name="`items[${index}][item_code]`" x-model="item.item_code" placeholder="0:0" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.shop_offer_level') }}</span><select x-bind:name="`items[${index}][level]`" x-model.number="item.level">@foreach ($itemLevels as $level)<option value="{{ $level }}">+{{ $level }}</option>@endforeach</select></label>
                                    <label class="field-group"><span>{{ __('ui.admin.shop_offer_durability') }}</span><input type="number" x-bind:name="`items[${index}][durability]`" x-model.number="item.durability" min="0" max="255" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.shop_offer_option') }}</span><select x-bind:name="`items[${index}][option_level]`" x-model.number="item.option_level">@foreach ($optionLevels as $level)<option value="{{ $level }}">+{{ $level }}</option>@endforeach</select></label>
                                    <label class="field-group"><span>{{ __('ui.admin.shop_offer_excellent_mask') }}</span><input type="number" x-bind:name="`items[${index}][excellent_mask]`" x-model.number="item.excellent_mask" min="0" max="63" required></label>
                                    <label class="field-group"><span>{{ __('ui.admin.shop_offer_weight') }}</span><input type="number" x-bind:name="`items[${index}][weight]`" x-model.number="item.weight" min="1" required></label>
                                    <div class="flex flex-wrap items-end gap-4 pb-2"><label class="flex items-center gap-2 text-sm text-silver"><input type="checkbox" value="1" x-bind:name="`items[${index}][has_skill]`" x-model="item.has_skill">{{ __('ui.admin.shop_offer_skill') }}</label><label class="flex items-center gap-2 text-sm text-silver"><input type="checkbox" value="1" x-bind:name="`items[${index}][has_luck]`" x-model="item.has_luck">{{ __('ui.admin.shop_offer_luck') }}</label></div>
                                    <input type="hidden" x-bind:name="`items[${index}][ancient_option]`" value="0">
                                </div>
                                <button type="button" class="mt-4 text-sm text-red-200" @click="removeItem(index)" x-show="items.length > 1">{{ __('ui.admin.shop_offer_remove_item') }}</button>
                            </div>
                        </template>
                    </fieldset>
                    <div class="flex flex-wrap justify-end gap-3 border-t border-white/8 pt-5"><a href="{{ route('admin.shop-offers.index') }}" class="button button-ghost">{{ __('ui.admin.shop_offers_heading') }}</a><button class="button button-primary">{{ __('ui.admin.shop_offer_save') }}</button></div>
                </form>
                @if ($offer)
                    <section class="panel-card mt-6 p-5 sm:p-7">
                        <p class="kicker">{{ __('ui.admin.shop_reward_grant') }}</p>
                        <p class="mt-2 text-sm text-steel">{{ __('ui.admin.shop_reward_grant_help') }}</p>
                        <form method="POST" action="{{ route('admin.shop-offers.grants.store', $offer) }}" class="mt-5 grid gap-4 md:grid-cols-[14rem_1fr_auto] md:items-end">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <label class="field-group"><span>{{ __('ui.admin.wallet_account_id') }}</span><input name="account_id" minlength="4" maxlength="10" pattern="[A-Za-z0-9_-]+" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.shop_reward_reason') }}</span><input name="reason" minlength="5" maxlength="500" required></label>
                            <button class="button button-primary">{{ __('ui.admin.shop_reward_grant') }}</button>
                        </form>
                    </section>
                    <form method="POST" action="{{ route('admin.shop-offers.destroy', $offer) }}" class="mt-5 flex justify-end" onsubmit="return confirm(@js(__('ui.admin.shop_offer_archive_confirm')))">@csrf @method('DELETE')<button class="button button-ghost text-red-200">{{ __('ui.admin.shop_offer_archive') }}</button></form>
                @endif
            </main>
        </div>
    </div>
@endsection
