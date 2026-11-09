@extends('layouts.app')

@section('title', __('ui.market.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.market.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.market.heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.market.intro', ['version' => $currentGameServerVersion]) }}</p>
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

        @auth
            @if ($auctionEnabled && $currentBids->isNotEmpty())
                <section id="your-current-bids" class="mt-8 scroll-mt-24">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="kicker">{{ __('ui.market.your_current_bids') }}</p>
                            <p class="mt-2 max-w-2xl text-sm text-silver">{{ __('ui.market.current_bids_intro') }}</p>
                        </div>
                        <span class="text-sm text-steel">{{ __('ui.market.current_bids_count', ['count' => $currentBids->total()]) }}</span>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        @foreach ($currentBids as $listing)
                            @php($item = $listing->item_snapshot)
                            @php($isLeading = $listing->highest_bidder_account_id === $currentAccountId)
                            @php($nextBid = ($listing->highest_bid_credits ?? ($listing->minimum_bid_credits - $listing->bid_increment_credits)) + $listing->bid_increment_credits)
                            <article
                                class="market-item-card panel-card grid gap-5 p-5 sm:grid-cols-[8rem_minmax(0,1fr)]"
                                x-data="itemInspector(@js($itemOptionLabels), @js($item))"
                            >
                                <div class="relative grid min-h-32 place-items-center rounded-2xl border border-white/8 bg-black/25 p-4">
                                    @if (($item['image'] ?? null) !== null)
                                        <img src="{{ $item['image'] }}" alt="{{ $item['name'] ?? __('ui.market.unknown_item') }}" class="max-h-24 max-w-full object-contain" loading="lazy">
                                    @endif
                                    <x-item-tooltip item="item" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p
                                                class="text-lg font-semibold"
                                                :class="`item-name-${itemNameTone(item)}`"
                                                x-text="itemTitle(item)"
                                            >{{ $item['name'] ?? __('ui.market.unknown_item') }}</p>
                                            <time class="mt-1 block text-xs text-steel" datetime="{{ $listing->expires_at?->toAtomString() }}">
                                                {{ __('ui.market.auction_ends') }} {{ $listing->expires_at?->diffForHumans() }}
                                            </time>
                                        </div>
                                        <span @class([
                                            'rounded-full border px-3 py-1 text-xs font-semibold',
                                            'border-emerald-300/30 bg-emerald-300/10 text-emerald-100' => $isLeading,
                                            'border-amber-300/30 bg-amber-300/10 text-amber-100' => ! $isLeading,
                                        ])>
                                            {{ $isLeading ? __('ui.market.bid_status_leading') : __('ui.market.bid_status_outbid') }}
                                        </span>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <div class="rounded-xl border border-white/8 bg-black/20 p-3">
                                            <p class="text-[0.68rem] uppercase tracking-[0.14em] text-steel">{{ __('ui.market.current_bid') }}</p>
                                            <p class="mt-1 font-semibold text-gold">{{ number_format($listing->highest_bid_credits) }} Credits</p>
                                        </div>
                                        <div class="rounded-xl border border-white/8 bg-black/20 p-3">
                                            <p class="text-[0.68rem] uppercase tracking-[0.14em] text-steel">{{ __('ui.market.your_last_bid') }}</p>
                                            <p class="mt-1 font-semibold text-white">{{ number_format($listing->current_account_last_bid_credits) }} Credits</p>
                                        </div>
                                    </div>

                                    @if ($isLeading)
                                        <p class="mt-4 text-sm text-emerald-100">{{ __('ui.market.bid_leading_help') }}</p>
                                    @else
                                        <form method="POST" action="{{ route('market.listings.bids.store', $listing) }}" class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                            @csrf
                                            <input type="hidden" name="idempotency_key" value="{{ $currentBidIdempotencyKeys[$listing->getKey()] }}">
                                            <input type="number" name="amount_credits" min="{{ $nextBid }}" value="{{ $nextBid }}" required>
                                            <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled)>{{ __('ui.market.bid_again') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($currentBids->hasPages())
                        <div class="mt-6">{{ $currentBids->links() }}</div>
                    @endif
                </section>
            @endif
        @endauth

        <section id="active-listings" class="mt-12 scroll-mt-24">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="kicker">{{ __('ui.market.live_market') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.market.active_listings') }}</h2>
                </div>
                <span class="text-sm text-steel">{{ __('ui.market.listing_count', ['count' => $listings->total()]) }}</span>
            </div>

            <form method="GET" action="{{ route('market.index') }}" class="panel-card mt-5 grid gap-4 p-4 md:grid-cols-[minmax(0,1fr)_18rem_auto] md:items-end" role="search">
                <label class="field-group">
                    <span>{{ __('ui.market.search_label') }}</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ $marketSearchTerm }}"
                        maxlength="80"
                        placeholder="{{ __('ui.market.search_placeholder') }}"
                    >
                </label>
                <label class="field-group">
                    <span>{{ __('ui.market.category_label') }}</span>
                    <select name="category">
                        <option value="">{{ __('ui.market.all_categories') }}</option>
                        @foreach ($marketCategories as $marketCategory)
                            <option value="{{ $marketCategory->value }}" @selected($selectedMarketCategory === $marketCategory)>
                                {{ __('ui.market.categories.'.$marketCategory->value) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="button button-primary grow md:grow-0">{{ __('ui.market.apply_filters') }}</button>
                    @if ($marketSearchTerm !== null || $selectedMarketCategory !== null)
                        <a href="{{ route('market.index') }}" class="button button-ghost grow md:grow-0">{{ __('ui.market.clear_filters') }}</a>
                    @endif
                </div>
            </form>

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
                        >{{ $item['name'] ?? __('ui.market.unknown_item') }}</p>
                        <p class="mt-1 text-xs uppercase tracking-[0.14em] text-steel">{{ ucfirst((string) ($item['rarity'] ?? 'normal')) }}</p>
                        @if ($listing->sale_type === \App\Market\MarketSaleType::Auction)
                            <div
                                class="mt-3 rounded-xl border border-amber-300/15 bg-amber-300/5 px-3 py-2"
                                x-data="marketExpiry(
                                    @js($listing->expires_at?->toAtomString()),
                                    @js($marketServerNow),
                                    @js(app()->getLocale()),
                                    @js(__('ui.market.ending_now'))
                                )"
                            >
                                <p class="text-xs text-amber-100">
                                    <span>{{ __('ui.market.auction_ends') }}</span>
                                    <strong class="font-semibold" x-text="relativeTime">{{ $listing->expires_at?->diffForHumans() }}</strong>
                                </p>
                                <time
                                    class="mt-1 block text-[0.68rem] uppercase tracking-[0.12em] text-steel"
                                    datetime="{{ $listing->expires_at?->toAtomString() }}"
                                    title="{{ __('ui.market.exact_end') }}"
                                >{{ $listing->expires_at?->translatedFormat('d M Y · H:i') }}</time>
                            </div>
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
                    <div class="panel-card p-10 text-center text-steel sm:col-span-2 xl:col-span-4">
                        {{ $marketSearchTerm !== null || $selectedMarketCategory !== null ? __('ui.market.no_matching_listings') : __('ui.market.no_listings') }}
                    </div>
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
