@extends('layouts.admin')

@section('title', __('ui.admin.market_title'))

@section('content')
    <div class="admin-shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.market_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.market_intro') }}</p>
        </div>

        <div class="mt-8">
            <main class="grid min-w-0 content-start gap-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->has('market'))
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first('market') }}</div>
                @endif
                @if (! $gameWritesEnabled)
                    <div class="rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">{{ __('ui.admin.market_writes_disabled') }}</div>
                @endif

                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($statuses as $status)
                        <a href="{{ route('admin.market.index', ['status' => $status->value]) }}" class="panel-card p-4 transition hover:border-gold/30">
                            <p class="text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.market.statuses.'.$status->value) }}</p>
                            <p class="mt-2 text-2xl font-bold text-white">{{ number_format((int) ($statusTotals[$status->value] ?? 0)) }}</p>
                        </a>
                    @endforeach
                </section>

                <section class="panel-card p-5 sm:p-6">
                    <form method="GET" action="{{ route('admin.market.index') }}" class="grid gap-4 md:grid-cols-[1fr_14rem_auto] md:items-end">
                        <label class="field-group">
                            <span>{{ __('ui.admin.market_search') }}</span>
                            <input type="search" name="search" value="{{ $search }}" maxlength="80" placeholder="{{ __('ui.admin.market_search_placeholder') }}">
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.market_status_filter') }}</span>
                            <select name="status">
                                <option value="">{{ __('ui.admin.market_all_statuses') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ __('ui.market.statuses.'.$status->value) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="button button-ghost" type="submit">{{ __('ui.admin.market_filter') }}</button>
                    </form>
                </section>

                <section class="panel-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                                <tr>
                                    <th class="px-5 py-4">{{ __('ui.market.item') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.market_parties') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.market.price') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.market.status') }}</th>
                                    <th class="px-5 py-4">{{ __('ui.admin.market_sync') }}</th>
                                    <th class="px-5 py-4"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/6">
                                @forelse ($listings as $listing)
                                    <tr>
                                        <td class="min-w-52 px-5 py-4">
                                            <p class="font-semibold text-white">{{ $listing->item_snapshot['name'] ?? __('ui.market.unknown_item') }}</p>
                                            <p class="mt-1 font-mono text-xs text-steel">{{ $listing->public_id }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-silver">
                                            <p>{{ __('ui.admin.market_seller') }}: <span class="text-white">{{ $listing->seller_account_id }}</span></p>
                                            @if ($listing->buyer_account_id)<p class="mt-1">{{ __('ui.admin.market_buyer') }}: <span class="text-white">{{ $listing->buyer_account_id }}</span></p>@endif
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-gold">{{ number_format($listing->price_credits) }}</td>
                                        <td class="whitespace-nowrap px-5 py-4 text-silver">{{ __('ui.market.statuses.'.$listing->status->value) }}</td>
                                        <td class="px-5 py-4 text-steel">
                                            <p>{{ $listing->sync_attempts }} {{ __('ui.admin.market_attempts') }}</p>
                                            @if ($listing->last_sync_error)<p class="mt-1 text-xs text-red-200">{{ $listing->last_sync_error }}</p>@endif
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex justify-end gap-2">
                                                @if (in_array($listing->status, [\App\Market\MarketListingStatus::Extracting, \App\Market\MarketListingStatus::DeliveryPending, \App\Market\MarketListingStatus::ReturnPending], true))
                                                    <form method="POST" action="{{ route('admin.market.retry', $listing) }}">
                                                        @csrf
                                                        <button class="button button-ghost" type="submit" @disabled(! $gameWritesEnabled)>{{ __('ui.admin.market_retry') }}</button>
                                                    </form>
                                                @endif
                                                @if ($listing->status === \App\Market\MarketListingStatus::Active && $listing->highest_bid_id === null)
                                                    <form method="POST" action="{{ route('admin.market.return', $listing) }}" onsubmit="return confirm(@js(__('ui.admin.market_return_confirm')))">
                                                        @csrf
                                                        <button class="button button-ghost text-amber-100" type="submit" @disabled(! $gameWritesEnabled)>{{ __('ui.admin.market_force_return') }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.market_empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-white/8 p-5">{{ $listings->links() }}</div>
                </section>

                <section class="panel-card overflow-hidden">
                    <div class="border-b border-white/8 p-5"><p class="kicker">{{ __('ui.admin.market_recent_actions') }}</p></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.14em] text-steel"><tr><th class="px-5 py-4">{{ __('ui.admin.wallet_time') }}</th><th class="px-5 py-4">{{ __('ui.admin.market_action') }}</th><th class="px-5 py-4">{{ __('ui.admin.market_listing') }}</th><th class="px-5 py-4">{{ __('ui.admin.market_transition') }}</th><th class="px-5 py-4">{{ __('ui.admin.market_operator') }}</th></tr></thead>
                            <tbody class="divide-y divide-white/6">
                                @forelse ($recentActions as $action)
                                    <tr><td class="px-5 py-4 text-steel">{{ $action->created_at?->format('Y-m-d H:i:s') }}</td><td class="px-5 py-4 text-white">{{ __('ui.admin.market_actions.'.$action->action) }} · {{ $action->outcome }}</td><td class="px-5 py-4 font-mono text-xs text-silver">{{ $action->listing?->public_id }}</td><td class="px-5 py-4 text-silver">{{ $action->before_status }} → {{ $action->after_status }}</td><td class="px-5 py-4 text-silver">{{ $action->administrator?->name ?? __('ui.admin.market_deleted_operator') }}</td></tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.market_no_actions') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
@endsection
