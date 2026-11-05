@extends('layouts.app')
@section('title', $type === \App\Shop\ShopOfferType::Item ? __('ui.shop.items_title') : __('ui.shop.boxes_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ $type === \App\Shop\ShopOfferType::Item ? __('ui.shop.items_eyebrow') : __('ui.shop.boxes_eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ $type === \App\Shop\ShopOfferType::Item ? __('ui.shop.items_heading') : __('ui.shop.boxes_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ $type === \App\Shop\ShopOfferType::Item ? __('ui.shop.items_intro') : __('ui.shop.boxes_intro') }}</p>
            </div>
            @auth
                <div class="panel-card px-5 py-4 text-right">
                    <p class="text-xs uppercase tracking-[0.16em] text-steel">{{ __('ui.shop.credits_balance') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gold">{{ number_format($balance) }}</p>
                </div>
            @endauth
        </header>

        <x-account-navigation class="mt-7" />
        <x-shop-navigation :active="$type === \App\Shop\ShopOfferType::Item ? 'items' : 'mystery-boxes'" class="mt-6" />

        <form method="GET" class="panel-card mt-6 grid gap-4 p-5 sm:grid-cols-[minmax(0,18rem)_auto] sm:items-end">
            <label class="field-group">
                <span>{{ __('ui.shop.all_classes') }}</span>
                <select name="class">
                    @foreach ($classScopes as $scope)
                        <option value="{{ $scope->value }}" @selected($selectedClassScope->value === $scope->value)>{{ __('ui.shop.class_scopes.'.$scope->value) }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="button button-ghost sm:justify-self-start">{{ __('ui.shop.filter') }}</button>
        </form>

        @if ($errors->any())
            <div class="mt-5 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($offers as $offer)
                @php($item = $type === \App\Shop\ShopOfferType::Item ? $offer->items->first() : null)
                <article class="panel-card flex min-h-72 flex-col p-6">
                    <div class="flex items-start justify-between gap-4">
                        <span class="resource-chip">{{ __('ui.shop.class_scopes.'.$offer->class_scope->value) }}</span>
                        @if ($item?->item_image)
                            <img src="{{ asset($item->item_image) }}" alt="" class="size-16 object-contain" loading="lazy">
                        @else
                            <div class="grid size-16 place-items-center rounded-2xl border border-gold/25 bg-gold/8 font-display text-2xl text-gold" aria-hidden="true">?</div>
                        @endif
                    </div>
                    <h2 class="mt-5 text-2xl font-semibold text-white">{{ $offer->localizedTitle(app()->getLocale()) }}</h2>
                    @if ($offer->localizedDescription(app()->getLocale()))
                        <p class="mt-3 text-sm leading-6 text-silver">{{ $offer->localizedDescription(app()->getLocale()) }}</p>
                    @endif
                    @if ($type === \App\Shop\ShopOfferType::MysteryBox)
                        <p class="mt-3 text-sm text-gold">{{ __('ui.shop.mystery_contents') }}</p>
                        <p class="mt-3 mb-4 inline-flex w-fit items-center rounded-full border border-sky-300/25 bg-sky-300/8 px-3 py-1.5 text-xs font-semibold text-sky-100">
                            {{ __('ui.shop.mystery_reward_modes.'.$offer->mystery_box_reward_mode->value) }}
                        </p>
                    @elseif ($item)
                        <p class="mt-3 text-sm text-gold">{{ $item->item_name }}{{ $item->level > 0 ? ' +'.$item->level : '' }}</p>
                    @endif
                    <div class="mt-auto flex items-end justify-between gap-4 border-t border-white/8 pt-5">
                        <div><span class="block text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.shop.price') }}</span><strong class="mt-1 block text-xl text-white">{{ number_format($offer->price_credits) }} Credits</strong></div>
                        <form method="POST" action="{{ $type === \App\Shop\ShopOfferType::Item ? route('shop.items.purchase', $offer) : route('shop.mystery-boxes.purchase', $offer) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ $purchaseKeys[$offer->public_id] }}">
                            <button type="submit" class="button button-primary">{{ __('ui.shop.buy') }}</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="panel-card p-10 text-center text-steel md:col-span-2 xl:col-span-3">{{ __('ui.shop.empty') }}</div>
            @endforelse
        </section>

        @if ($offers->hasPages())
            <div class="mt-7">{{ $offers->links() }}</div>
        @endif
    </div>
@endsection
