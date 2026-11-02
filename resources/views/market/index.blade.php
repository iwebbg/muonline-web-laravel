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
                <a href="#sell-items" class="button button-primary">{{ __('ui.market.sell_item') }}</a>
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
                    <article class="panel-card flex min-h-64 flex-col p-5">
                        <div class="grid min-h-28 place-items-center rounded-2xl border border-white/8 bg-black/25 p-4">
                            @if (($item['image'] ?? null) !== null)
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] ?? __('ui.market.unknown_item') }}" class="max-h-24 max-w-full object-contain" loading="lazy">
                            @endif
                        </div>
                        <p class="mt-4 text-lg font-semibold text-white">{{ $item['name'] ?? __('ui.market.unknown_item') }}{{ ($item['level'] ?? 0) > 0 ? ' +'.$item['level'] : '' }}</p>
                        <p class="mt-1 text-xs uppercase tracking-[0.14em] text-steel">{{ $item['code'] ?? '' }} · {{ ucfirst((string) ($item['rarity'] ?? 'normal')) }}</p>
                        <div class="mt-auto flex items-end justify-between gap-4 pt-5">
                            <div>
                                <p class="text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.market.price') }}</p>
                                <p class="mt-1 text-xl font-bold text-gold">{{ number_format($listing->price_credits) }} Credits</p>
                            </div>
                            <span class="rounded-full border border-white/8 px-3 py-1 text-xs text-steel">{{ __('ui.market.escrowed') }}</span>
                        </div>
                    </article>
                @empty
                    <div class="panel-card p-10 text-center text-steel sm:col-span-2 xl:col-span-4">{{ __('ui.market.no_listings') }}</div>
                @endforelse
            </div>

            <div class="mt-6">{{ $listings->links() }}</div>
        </section>

        @auth
            <section id="sell-items" class="mt-12 scroll-mt-28">
                <div>
                    <p class="kicker">{{ __('ui.market.seller_workspace') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.market.warehouse_escrow') }}</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-silver">{{ __('ui.market.escrow_help') }}</p>
                </div>

                <form method="GET" action="{{ route('market.index') }}" class="panel-card mt-5 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                    <label class="field-group flex-1">
                        <span>{{ __('ui.market.character') }}</span>
                        <select name="character" required>
                            @foreach ($characters as $character)
                                <option value="{{ $character['name'] }}" @selected($selectedCharacter === $character['name'])>
                                    {{ $character['name'] }}{{ $character['online'] ? ' · '.__('ui.market.online') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="button button-ghost">{{ __('ui.market.load_warehouse') }}</button>
                </form>

                @if (! $gameWritesEnabled)
                    <div class="mt-5 rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">{{ __('ui.market.writes_disabled') }}</div>
                @endif

                <div class="mt-5 grid gap-6">
                    @forelse ($warehouseVaults as $vault)
                        <article class="panel-card p-5 sm:p-7">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-xl font-semibold text-white">{{ __('ui.market.vault', ['number' => $vault->number + 1]) }}</h3>
                                @if ($vault->active)<span class="text-xs uppercase tracking-[0.14em] text-emerald-300">{{ __('ui.market.active_vault') }}</span>@endif
                            </div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                @forelse ($vault->items as $item)
                                    <form method="POST" action="{{ route('market.listings.store') }}" class="rounded-2xl border border-white/8 bg-black/20 p-4">
                                        @csrf
                                        <input type="hidden" name="character_name" value="{{ $selectedCharacter }}">
                                        <input type="hidden" name="vault" value="{{ $vault->number }}">
                                        <input type="hidden" name="slot" value="{{ $item->slot }}">
                                        <input type="hidden" name="idempotency_key" value="{{ $listingIdempotencyKey }}">
                                        <div class="flex min-h-20 items-center gap-4">
                                            @if ($item->image !== null)
                                                <img src="{{ $item->image }}" alt="{{ $item->name }}" class="size-16 object-contain" loading="lazy">
                                            @endif
                                            <div>
                                                <strong class="text-white">{{ $item->name }}{{ ($item->level ?? 0) > 0 ? ' +'.$item->level : '' }}</strong>
                                                <p class="mt-1 text-xs text-steel">{{ $item->code() }} · {{ ucfirst($item->rarity()) }}</p>
                                            </div>
                                        </div>
                                        <label class="field-group mt-4">
                                            <span>{{ __('ui.market.price_credits') }}</span>
                                            <input type="number" name="price_credits" min="1" max="1000000000" value="100" required>
                                        </label>
                                        <button type="submit" class="button button-primary mt-4 w-full" @disabled(! $gameWritesEnabled)>{{ __('ui.market.move_to_escrow') }}</button>
                                    </form>
                                @empty
                                    <p class="text-sm text-steel">{{ __('ui.market.empty_vault') }}</p>
                                @endforelse
                            </div>
                        </article>
                    @empty
                        <div class="panel-card p-8 text-center text-steel">{{ __('ui.market.no_warehouse') }}</div>
                    @endforelse
                </div>
            </section>

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
                                    <td class="px-5 py-4 text-silver">{{ __('ui.market.statuses.'.$listing->status->value) }}</td>
                                    <td class="px-5 py-4 text-right">
                                        @if ($listing->status === \App\Market\MarketListingStatus::Active)
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
        @endauth
    </div>
@endsection
