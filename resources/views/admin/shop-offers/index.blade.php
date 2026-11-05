@extends('layouts.app')

@section('title', __('ui.admin.shop_offers_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.shop_offers_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.shop_offers_intro') }}</p>
            </div>
            <a href="{{ route('admin.shop-offers.create') }}" class="button button-primary">{{ __('ui.admin.shop_offer_create') }}</a>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="shop-offers" />
            <main class="grid min-w-0 content-start gap-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                @endif
                <form method="GET" class="panel-card grid gap-4 p-5 md:grid-cols-[1fr_14rem_auto] md:items-end">
                    <label class="field-group"><span>{{ __('ui.admin.shop_offer_search') }}</span><input type="search" name="search" value="{{ $search }}" maxlength="120"></label>
                    <label class="field-group"><span>{{ __('ui.admin.shop_offer_type') }}</span><select name="type"><option value="">{{ __('ui.admin.shop_offer_all_types') }}</option>@foreach ($types as $type)<option value="{{ $type->value }}" @selected($selectedType === $type)>{{ __('ui.admin.shop_offer_types.'.$type->value) }}</option>@endforeach</select></label>
                    <button class="button button-ghost">{{ __('ui.admin.shop_offer_filter') }}</button>
                </form>

                <section class="grid gap-4">
                    @forelse ($offers as $offer)
                        <article class="panel-card flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div>
                                <div class="flex flex-wrap items-center gap-3"><span class="resource-chip">{{ __('ui.admin.shop_offer_types.'.$offer->type->value) }}</span><span class="text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.admin.shop_offer_statuses.'.$offer->status->value) }}</span></div>
                                <h2 class="mt-3 text-xl font-semibold text-white">{{ $offer->title_en }}</h2>
                                <p class="mt-2 text-sm text-silver">{{ number_format($offer->price_credits) }} Credits · {{ $offer->items_count }} {{ __('ui.admin.shop_offer_items') }}</p>
                            </div>
                            <a href="{{ route('admin.shop-offers.edit', $offer) }}" class="button button-ghost">{{ __('ui.admin.shop_offer_edit') }}</a>
                        </article>
                    @empty
                        <div class="panel-card p-10 text-center text-steel">{{ __('ui.admin.shop_offer_empty') }}</div>
                    @endforelse
                </section>
                @if ($offers->hasPages())<div>{{ $offers->links() }}</div>@endif
            </main>
        </div>
    </div>
@endsection
