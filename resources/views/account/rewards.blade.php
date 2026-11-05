@extends('layouts.app')

@section('title', __('ui.rewards.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <x-account-feature-header :kicker="__('ui.rewards.heading')" :heading="__('ui.rewards.heading')" :intro="__('ui.rewards.intro')" :show-back="false" />
        <x-account-navigation class="mt-6" />
        <x-shop-navigation active="rewards" class="mt-6" />

        @if (session('status'))
            <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ session('error') }}</div>
        @endif

        <section class="mt-8 grid gap-5 lg:grid-cols-2">
            @forelse ($rewards as $reward)
                @php($item = $reward->selected_item_snapshot)
                <article class="panel-card p-6">
                    <div class="flex items-start justify-between gap-5">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="resource-chip">{{ __('ui.rewards.'.$reward->status->value) }}</span>
                                <span class="text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.rewards.sources.'.$reward->source->value) }}</span>
                            </div>
                            <h2 class="mt-4 text-xl font-semibold text-white">{{ $reward->localizedTitle(app()->getLocale()) }}</h2>
                            <p class="mt-2 text-sm text-gold">{{ is_array($item) ? ($item['item_name'] ?? __('ui.rewards.hidden_item')) : __('ui.rewards.hidden_item') }}</p>
                            @if ($reward->reward_type === \App\Shop\ShopOfferType::MysteryBox)
                                <p class="mt-2 text-xs font-semibold text-sky-100">{{ __('ui.shop.mystery_reward_modes.'.$reward->mystery_box_reward_mode->value) }}</p>
                            @endif
                        </div>
                        @if (is_array($item) && ($item['item_image'] ?? null))
                            <img src="{{ asset($item['item_image']) }}" alt="" class="size-16 object-contain">
                        @else
                            <div class="grid size-16 shrink-0 place-items-center rounded-2xl border border-gold/25 bg-gold/8 font-display text-2xl text-gold">?</div>
                        @endif
                    </div>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-white/8 pt-5">
                        <div class="text-sm text-steel">
                            @if ($reward->delivery_destination)
                                {{ __('ui.rewards.destinations.'.$reward->delivery_destination->value) }}
                            @elseif ($reward->last_delivery_error)
                                {{ __('ui.rewards.delivery_pending') }}
                            @endif
                        </div>
                        @if ($reward->status === \App\Shop\AccountRewardStatus::Sealed)
                            <form method="POST" action="{{ route('account.rewards.open', $reward) }}">@csrf<button class="button button-primary">{{ __('ui.rewards.open') }}</button></form>
                        @elseif ($reward->status === \App\Shop\AccountRewardStatus::Ready)
                            <form method="POST" action="{{ route('account.rewards.deliver', $reward) }}">@csrf<button class="button button-primary">{{ __('ui.rewards.retry_delivery') }}</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="panel-card p-10 text-center text-steel lg:col-span-2">{{ __('ui.rewards.empty') }}</div>
            @endforelse
        </section>

        @if ($rewards->hasPages())
            <div class="mt-7">{{ $rewards->links() }}</div>
        @endif
    </div>
@endsection
