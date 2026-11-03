@extends('layouts.app')

@section('title', __('ui.market.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.market.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.market.heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.market.intro') }}</p>
            </div>
            @auth
                <a href="{{ route('account.dashboard', ['tab' => 'vaults']) }}" class="button button-primary">{{ __('ui.market.sell_item') }}</a>
            @else
                <a href="{{ route('login') }}" class="button button-primary">{{ __('ui.market.sign_in_to_sell') }}</a>
            @endauth
        </div>

        @if (session('marketStatus'))
            <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                {{ session('marketStatus') }}
            </div>
        @endif

        @if ($errors->has('market'))
            <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                {{ $errors->first('market') }}
            </div>
        @endif

        <section class="mt-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="kicker">{{ __('ui.market.live_market') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.market.active_listings') }}</h2>
                </div>
                <span class="text-sm text-steel">{{ __('ui.market.listing_count', ['count' => $listings->total()]) }}</span>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($listings as $listing)
                    @php($item = $listing->item_snapshot)
                    <article
                        class="market-item-card panel-card flex min-h-64 flex-col p-5"
                        x-data="itemInspector(@js($itemOptionLabels), @js($item))"
                    >
                        <div class="relative grid min-h-28 place-items-center rounded-2xl border border-white/8 bg-black/25 p-4">
                            @if (($item['image'] ?? null) !== null)
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] ?? __('ui.market.unknown_item') }}" class="max-h-24 max-w-full object-contain" loading="lazy">
                            @endif
                            <x-item-tooltip item="item" />
                        </div>
                        <p
                            class="mt-4 text-lg font-semibold"
                            :class="`item-name-${itemNameTone(item)}`"
                            x-text="itemTitle(item)"
                        ></p>
                        <p class="mt-1 text-xs uppercase tracking-[0.14em] text-steel">{{ ucfirst((string) ($item['rarity'] ?? 'normal')) }}</p>
                        @if ($listing->sale_type === \App\Market\MarketSaleType::Auction)
                            <p class="mt-2 text-xs text-amber-100">{{ __('ui.market.ends_at') }} {{ $listing->expires_at?->format('Y-m-d H:i') }}</p>
                        @endif
                        <div class="mt-auto flex items-end justify-between gap-4 pt-5">
                            <div>
                                <p class="text-xs uppercase tracking-[0.14em] text-steel">{{ $listing->sale_type === \App\Market\MarketSaleType::Auction ? __('ui.market.current_bid') : __('ui.market.price') }}</p>
                                <p class="mt-1 text-xl font-bold text-gold">{{ number_format($listing->highest_bid_credits ?? $listing->price_credits) }} Credits</p>
                            </div>
                            <span class="rounded-full border border-white/8 px-3 py-1 text-xs text-steel">{{ __('ui.market.escrowed') }}</span>
                        </div>
                        @auth
                            @if ($listing->seller_account_id !== $currentAccountId && $listing->sale_type === \App\Market\MarketSaleType::Fixed)
                                <form method="POST" action="{{ route('market.listings.purchase', $listing) }}" class="mt-4">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ $purchaseIdempotencyKeys[$listing->getKey()] }}">
                                    <button type="submit" class="button button-primary w-full" @disabled(! $gameWritesEnabled)>
                                        {{ __('ui.market.buy_now') }}
                                    </button>
                                </form>
                            @elseif ($listing->seller_account_id !== $currentAccountId)
                                <form method="POST" action="{{ route('market.listings.bids.store', $listing) }}" class="mt-4 grid grid-cols-[1fr_auto] gap-2">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ $bidIdempotencyKeys[$listing->getKey()] }}">
                                    <input type="number" name="amount_credits" min="{{ ($listing->highest_bid_credits ?? ($listing->minimum_bid_credits - $listing->bid_increment_credits)) + $listing->bid_increment_credits }}" value="{{ ($listing->highest_bid_credits ?? ($listing->minimum_bid_credits - $listing->bid_increment_credits)) + $listing->bid_increment_credits }}" required>
                                    <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled)>{{ __('ui.market.place_bid') }}</button>
                                </form>
                            @else
                                <p class="mt-4 text-center text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.market.your_listing') }}</p>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="button button-ghost mt-4 w-full">{{ __('ui.market.sign_in_to_buy') }}</a>
                        @endauth
                    </article>
                @empty
                    <div class="panel-card p-10 text-center text-steel sm:col-span-2 xl:col-span-4">{{ __('ui.market.no_listings') }}</div>
                @endforelse
            </div>

            <div class="mt-6">{{ $listings->links() }}</div>
        </section>

        @auth
            <section class="mt-12">
                <p class="kicker">{{ __('ui.market.my_listings') }}</p>
                <div class="mt-5 overflow-x-auto rounded-2xl border border-white/8 bg-black/20">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                            <tr>
                                <th class="px-5 py-4">{{ __('ui.market.item') }}</th>
                                <th class="px-5 py-4">{{ __('ui.market.price') }}</th>
                                <th class="px-5 py-4">{{ __('ui.market.status') }}</th>
                                <th class="px-5 py-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/6">
                            @forelse ($myListings as $listing)
                                <tr>
                                    <td class="px-5 py-4 text-white">{{ $listing->item_snapshot['name'] ?? __('ui.market.unknown_item') }}</td>
                                    <td class="px-5 py-4 text-gold">{{ number_format($listing->price_credits) }}</td>
                                    <td class="px-5 py-4 text-silver">
                                        <span class="block">{{ __('ui.market.statuses.'.$listing->status->value) }}</span>
                                        @if ($listing->storage_destination !== null)
                                            <span class="mt-1 block text-xs text-steel">{{ __('ui.market.destinations.'.$listing->storage_destination->value) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        @if ($listing->status === \App\Market\MarketListingStatus::Active && $listing->highest_bid_id === null)
                                            <form method="POST" action="{{ route('market.listings.destroy', $listing) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="button button-ghost">{{ __('ui.market.return_item') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center text-steel">{{ __('ui.market.no_personal_listings') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-12">
                <p class="kicker">{{ __('ui.market.my_purchases') }}</p>
                <div class="mt-5 overflow-x-auto rounded-2xl border border-white/8 bg-black/20">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                            <tr>
                                <th class="px-5 py-4">{{ __('ui.market.item') }}</th>
                                <th class="px-5 py-4">{{ __('ui.market.price') }}</th>
                                <th class="px-5 py-4">{{ __('ui.market.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/6">
                            @forelse ($myPurchases as $listing)
                                <tr>
                                    <td class="px-5 py-4 text-white">{{ $listing->item_snapshot['name'] ?? __('ui.market.unknown_item') }}</td>
                                    <td class="px-5 py-4 text-gold">{{ number_format($listing->price_credits) }}</td>
                                    <td class="px-5 py-4 text-silver">
                                        <span class="block">{{ __('ui.market.statuses.'.$listing->status->value) }}</span>
                                        @if ($listing->storage_destination !== null)
                                            <span class="mt-1 block text-xs text-steel">{{ __('ui.market.destinations.'.$listing->storage_destination->value) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-10 text-center text-steel">{{ __('ui.market.no_purchases') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endauth
    </div>
@endsection
