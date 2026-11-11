@extends('layouts.app')

@section('title', __('ui.jewel_bank.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <x-account-feature-header
            :kicker="__('ui.jewel_bank.kicker')"
            :heading="__('ui.jewel_bank.heading')"
            :intro="__('ui.jewel_bank.intro')"
            :show-back="false"
        />
        <x-account-navigation class="mt-6" />
        <x-account-operation-feedback
            status-session="jewelBankStatus"
            error-key="jewelBank"
            :writes-enabled="$gameWritesEnabled"
            :writes-disabled-message="__('ui.web_storage.writes_disabled')"
        />

        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($jewelDefinitions as $code => $slug)
                <article class="panel-card p-5">
                    <p class="kicker">{{ __('ui.jewel_bank.jewels.'.$slug) }}</p>
                    <strong class="mt-3 block text-3xl text-white">{{ number_format($jewelCounts[$code]) }}</strong>
                    <span class="mt-1 block text-xs text-steel">{{ __('ui.jewel_bank.physical_items') }}</span>
                </article>
            @endforeach
        </section>

        <section class="mt-12">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="kicker">{{ __('ui.jewel_bank.bank_items') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.jewel_bank.available_heading') }}</h2>
                </div>
                <p class="text-sm text-steel">{{ __('ui.jewel_bank.available_total', ['quantity' => number_format(array_sum(array_column($storedJewelGroups, 'quantity')))]) }}</p>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($storedJewelGroups as $storedJewelGroup)
                    @php
                        $storedJewel = $storedJewelGroup['item'];
                        $itemSnapshot = $storedJewel->item_snapshot;
                        $quantity = $storedJewelGroup['quantity'];
                        $jewelCode = (string) ($itemSnapshot['code'] ?? '');
                        $jewelLevel = (int) ($itemSnapshot['level'] ?? 0);
                        $itemTitle = ($itemSnapshot['name'] ?? __('ui.market.unknown_item')).($jewelLevel > 0 ? ' +'.$jewelLevel : '');
                        $modalTitleId = 'jewel-withdraw-title-'.$storedJewel->public_id;
                    @endphp
                    <article class="panel-card flex flex-col p-5" x-data="{ withdrawModalOpen: false, withdrawDestination: 'warehouse' }">
                        <div class="grid min-h-28 place-items-center rounded-2xl border border-white/8 bg-black/25 p-4">
                            @if (($itemSnapshot['image'] ?? null) !== null)
                                <img src="{{ $itemSnapshot['image'] }}" alt="{{ $itemTitle }}" class="max-h-24 max-w-full object-contain" loading="lazy">
                            @endif
                        </div>
                        <div class="mt-4 flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-white">{{ $itemTitle }}</h3>
                                <p class="mt-1 text-xs text-steel">{{ __('ui.jewel_bank.choose_destination_hint') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full border border-emerald-300/20 bg-emerald-300/8 px-2.5 py-1 text-xs font-semibold text-emerald-200">×{{ $quantity }}</span>
                        </div>

                        <button type="button" class="button button-primary mt-5 w-full" @click="withdrawModalOpen = true" @disabled(! $gameWritesEnabled)>
                            {{ $quantity === 1 ? __('ui.jewel_bank.withdraw_one') : __('ui.jewel_bank.withdraw_quantity') }}
                        </button>

                        <template x-teleport="body">
                            <div
                                class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto bg-black/85 p-4 backdrop-blur-sm sm:p-6"
                                x-show="withdrawModalOpen"
                                x-transition.opacity
                                x-cloak
                                @click.self="withdrawModalOpen = false"
                                @keydown.escape.window="withdrawModalOpen = false"
                            >
                                <section class="panel-card w-full max-w-lg p-6 shadow-2xl shadow-black/70" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}">
                                    <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                                        <div>
                                            <p class="kicker">{{ $itemTitle }}</p>
                                            <h2 id="{{ $modalTitleId }}" class="mt-2 text-2xl font-semibold text-white">{{ __('ui.jewel_bank.withdraw_heading') }}</h2>
                                        </div>
                                        <button type="button" class="icon-button" aria-label="{{ __('ui.jewel_bank.cancel') }}" @click="withdrawModalOpen = false">×</button>
                                    </div>

                                    <div class="mt-6 flex items-center gap-4 rounded-2xl border border-white/8 bg-black/25 p-4">
                                        @if (($itemSnapshot['image'] ?? null) !== null)
                                            <img src="{{ $itemSnapshot['image'] }}" alt="{{ $itemTitle }}" class="size-20 object-contain" loading="lazy">
                                        @endif
                                        <div>
                                            <strong class="text-lg text-white">{{ $itemTitle }}</strong>
                                            <p class="mt-1 text-sm text-steel">{{ __('ui.jewel_bank.available_quantity', ['quantity' => $quantity]) }}</p>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('account.jewel-bank.withdrawals.store') }}" class="mt-6 space-y-4">
                                        @csrf
                                        <input type="hidden" name="jewel_code" value="{{ $jewelCode }}">
                                        <input type="hidden" name="jewel_level" value="{{ $jewelLevel }}">
                                        <label class="field-group">
                                            <span>{{ __('ui.jewel_bank.quantity') }}</span>
                                            <input type="number" name="quantity" value="1" min="1" max="{{ $quantity }}" required>
                                        </label>
                                        <label class="field-group">
                                            <span>{{ __('ui.jewel_bank.destination') }}</span>
                                            <select name="destination_location" x-model="withdrawDestination" required>
                                                <option value="warehouse">{{ __('ui.account.main_vault') }}</option>
                                                <option value="inventory">{{ __('ui.jewel_bank.character_inventory_option') }}</option>
                                            </select>
                                        </label>
                                        <label class="field-group" x-show="withdrawDestination === 'inventory'" x-cloak>
                                            <span>{{ __('ui.jewel_bank.select_character_bag') }}</span>
                                            <select
                                                name="character_name"
                                                :disabled="withdrawDestination !== 'inventory'"
                                                :required="withdrawDestination === 'inventory'"
                                            >
                                                @foreach ($characters as $character)
                                                    <option value="{{ $character->name }}">{{ $character->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <p class="text-sm leading-6 text-steel" x-show="withdrawDestination === 'warehouse'">
                                            {{ __('ui.jewel_bank.withdraw_to_main_vault_help') }}
                                        </p>
                                        <p class="text-sm leading-6 text-steel" x-show="withdrawDestination === 'inventory'" x-cloak>
                                            {{ __('ui.jewel_bank.withdraw_to_character_help') }}
                                        </p>
                                        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                                            <button type="button" class="button button-ghost" @click="withdrawModalOpen = false">{{ __('ui.jewel_bank.cancel') }}</button>
                                            <button type="submit" class="button button-primary">{{ __('ui.jewel_bank.withdraw_quantity') }}</button>
                                        </div>
                                    </form>
                                </section>
                            </div>
                        </template>
                    </article>
                @empty
                    <div class="panel-card p-10 text-center text-steel sm:col-span-2 xl:col-span-4">
                        {{ __('ui.jewel_bank.empty') }}
                    </div>
                @endforelse
            </div>
        </section>

        <section id="game-jewels" class="mt-12 scroll-mt-28">
            <p class="kicker">{{ __('ui.jewel_bank.game_jewels') }}</p>
            <form method="GET" action="{{ route('account.jewel-bank.index') }}#game-jewels" class="panel-card mt-5 grid gap-4 p-5 lg:grid-cols-[1fr_2fr_auto] lg:items-end">
                <label class="field-group">
                    <span>{{ __('ui.jewel_bank.source') }}</span>
                    <select name="source" x-on:change="$el.form.requestSubmit()">
                        <option value="warehouse" @selected($sourceLocation === \App\WebStorage\ItemStorageLocation::Warehouse)>
                            {{ __('ui.jewel_bank.game_vault') }}
                        </option>
                        <option value="inventory" @selected($sourceLocation === \App\WebStorage\ItemStorageLocation::Inventory)>
                            {{ __('ui.jewel_bank.character_bag') }}
                        </option>
                    </select>
                </label>

                @if ($sourceLocation === \App\WebStorage\ItemStorageLocation::Warehouse)
                    <label class="field-group">
                        <span>{{ __('ui.jewel_bank.select_vault') }}</span>
                        <select name="vault" x-on:change="$el.form.requestSubmit()" required>
                            @foreach ($vaults as $vault)
                                <option value="{{ $vault->number }}" @selected($selectedVault?->number === $vault->number)>
                                    {{ $vault->active ? __('ui.account.main_vault') : __('ui.market.vault', ['number' => $vault->number]) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <label class="field-group">
                        <span>{{ __('ui.jewel_bank.select_character_bag') }}</span>
                        <select name="character" x-on:change="$el.form.requestSubmit()" required>
                            @foreach ($characters as $character)
                                <option value="{{ $character->name }}" @selected($selectedCharacter === $character->name)>
                                    {{ $character->name }}{{ $character->online ? ' · '.__('ui.market.online') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <button type="submit" class="button button-ghost">{{ __('ui.jewel_bank.load_items') }}</button>
            </form>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($eligibleJewelGroups as $eligibleJewelGroup)
                    @php
                        $item = $eligibleJewelGroup['item'];
                        $quantity = $eligibleJewelGroup['quantity'];
                        $modalTitleId = 'jewel-deposit-title-'.str_replace(':', '-', $item->code()).'-'.($item->level ?? 0);
                        $itemTitle = $item->name.(($item->level ?? 0) > 0 ? ' +'.$item->level : '');
                    @endphp
                    <article class="panel-card p-5" x-data="{ depositModalOpen: false }">
                        <div class="flex min-h-20 items-center gap-4">
                            <img src="{{ $item->image }}" alt="{{ $itemTitle }}" class="size-16 object-contain" loading="lazy">
                            <div class="min-w-0 flex-1">
                                <strong class="text-white">{{ $itemTitle }}</strong>
                                <p class="mt-1 text-xs text-steel">
                                    @if ($sourceLocation === \App\WebStorage\ItemStorageLocation::Inventory)
                                        {{ __('ui.jewel_bank.bag_of', ['character' => $selectedCharacter]) }}
                                    @else
                                        {{ $selectedVault?->active ? __('ui.account.main_vault') : __('ui.market.vault', ['number' => $selectedVault?->number ?? 0]) }}
                                    @endif
                                </p>
                                <span class="mt-2 inline-flex rounded-full border border-amber-300/20 bg-amber-300/8 px-2.5 py-1 text-xs font-semibold text-amber-200">
                                    {{ __('ui.jewel_bank.available_quantity', ['quantity' => $quantity]) }}
                                </span>
                            </div>
                        </div>

                        @if ($quantity === 1)
                            <form method="POST" action="{{ route('account.jewel-bank.store') }}" class="mt-4">
                                @csrf
                                <input type="hidden" name="character_name" value="{{ $selectedCharacter }}">
                                <input type="hidden" name="source_location" value="{{ $sourceLocation->value }}">
                                <input type="hidden" name="vault" value="{{ $selectedVault?->number ?? 0 }}">
                                <input type="hidden" name="jewel_code" value="{{ $item->code() }}">
                                <input type="hidden" name="jewel_level" value="{{ $item->level ?? 0 }}">
                                <input type="hidden" name="quantity" value="1">
                                <input type="hidden" name="idempotency_key" value="{{ $eligibleJewelGroup['idempotencyKey'] }}">
                                <button type="submit" class="button button-primary w-full" @disabled(! $gameWritesEnabled || ! is_string($selectedCharacter))>
                                    {{ __('ui.jewel_bank.deposit_one') }}
                                </button>
                            </form>
                        @else
                            <button
                                type="button"
                                class="button button-primary mt-4 w-full"
                                @click="depositModalOpen = true"
                                @disabled(! $gameWritesEnabled || ! is_string($selectedCharacter))
                            >
                                {{ __('ui.jewel_bank.deposit_quantity') }}
                            </button>

                            <template x-teleport="body">
                                <div
                                    class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto bg-black/85 p-4 backdrop-blur-sm sm:p-6"
                                    x-show="depositModalOpen"
                                    x-transition.opacity
                                    x-cloak
                                    @click.self="depositModalOpen = false"
                                    @keydown.escape.window="depositModalOpen = false"
                                >
                                    <section class="panel-card w-full max-w-lg p-6 shadow-2xl shadow-black/70" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}">
                                        <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                                            <div>
                                                <p class="kicker">{{ $itemTitle }}</p>
                                                <h2 id="{{ $modalTitleId }}" class="mt-2 text-2xl font-semibold text-white">
                                                    {{ __('ui.jewel_bank.deposit_heading') }}
                                                </h2>
                                            </div>
                                            <button type="button" class="icon-button" aria-label="{{ __('ui.jewel_bank.cancel') }}" @click="depositModalOpen = false">×</button>
                                        </div>

                                        <div class="mt-6 flex items-center gap-4 rounded-2xl border border-white/8 bg-black/25 p-4">
                                            <img src="{{ $item->image }}" alt="{{ $itemTitle }}" class="size-20 object-contain" loading="lazy">
                                            <div>
                                                <strong class="text-lg text-white">{{ $itemTitle }}</strong>
                                                <p class="mt-1 text-sm text-steel">{{ __('ui.jewel_bank.available_quantity', ['quantity' => $quantity]) }}</p>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('account.jewel-bank.store') }}" class="mt-6">
                                            @csrf
                                            <input type="hidden" name="character_name" value="{{ $selectedCharacter }}">
                                            <input type="hidden" name="source_location" value="{{ $sourceLocation->value }}">
                                            <input type="hidden" name="vault" value="{{ $selectedVault?->number ?? 0 }}">
                                            <input type="hidden" name="jewel_code" value="{{ $item->code() }}">
                                            <input type="hidden" name="jewel_level" value="{{ $item->level ?? 0 }}">
                                            <input type="hidden" name="idempotency_key" value="{{ $eligibleJewelGroup['idempotencyKey'] }}">

                                            <label class="field-group">
                                                <span>{{ __('ui.jewel_bank.quantity') }}</span>
                                                <input type="number" name="quantity" value="1" min="1" max="{{ $quantity }}" required>
                                            </label>
                                            <p class="mt-3 text-sm leading-6 text-steel">{{ __('ui.jewel_bank.deposit_help') }}</p>

                                            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                                <button type="button" class="button button-ghost" @click="depositModalOpen = false">
                                                    {{ __('ui.jewel_bank.cancel') }}
                                                </button>
                                                <button type="submit" class="button button-primary">
                                                    {{ __('ui.jewel_bank.deposit_quantity') }}
                                                </button>
                                            </div>
                                        </form>
                                    </section>
                                </div>
                            </template>
                        @endif
                    </article>
                @empty
                    <div class="panel-card p-8 text-center text-steel sm:col-span-2 xl:col-span-4">
                        {{ __('ui.jewel_bank.no_game_jewels') }}
                    </div>
                @endforelse
            </div>
        </section>

        <section class="mt-12">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="kicker">{{ __('ui.jewel_bank.history_kicker') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.jewel_bank.history_heading') }}</h2>
                </div>
                <p class="text-sm text-steel">{{ __('ui.jewel_bank.history_count', ['count' => number_format($jewelHistory->total())]) }}</p>
            </div>

            <div class="panel-card mt-5 overflow-x-auto p-0">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/8 bg-black/20 text-xs uppercase tracking-[0.14em] text-steel">
                        <tr>
                            <th class="px-5 py-4">{{ __('ui.jewel_bank.history_time') }}</th>
                            <th class="px-5 py-4">{{ __('ui.jewel_bank.history_item') }}</th>
                            <th class="px-5 py-4">{{ __('ui.jewel_bank.history_source') }}</th>
                            <th class="px-5 py-4">{{ __('ui.jewel_bank.history_status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/6">
                        @forelse ($jewelHistory as $historyItem)
                            @php
                                $historySnapshot = $historyItem->item_snapshot;
                                $historyLevel = (int) ($historySnapshot['level'] ?? 0);
                                $historyTitle = ($historySnapshot['name'] ?? __('ui.market.unknown_item')).($historyLevel > 0 ? ' +'.$historyLevel : '');
                                $historyDestination = __('ui.jewel_bank.in_bank');

                                if ($historyItem->withdrawal_location === \App\WebStorage\ItemStorageLocation::Inventory) {
                                    $historyDestination = __('ui.jewel_bank.character_inventory', ['character' => $historyItem->withdrawal_character_name]);
                                } elseif ($historyItem->withdrawal_location === \App\WebStorage\ItemStorageLocation::Warehouse) {
                                    $historyDestination = __('ui.account.main_vault');
                                }
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-steel">{{ $historyItem->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="min-w-56 px-5 py-4 text-white">{{ $historyTitle }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-silver">
                                    {{ $historyDestination }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-steel">{{ __('ui.web_storage.statuses.'.$historyItem->status->value) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-12 text-center text-steel">{{ __('ui.jewel_bank.history_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($jewelHistory->hasPages())
                <div class="mt-5 flex items-center justify-between gap-4">
                    <a
                        href="{{ $jewelHistory->previousPageUrl() ?? '#' }}"
                        @class(['button button-ghost', 'pointer-events-none opacity-40' => $jewelHistory->onFirstPage()])
                        @if ($jewelHistory->onFirstPage()) aria-disabled="true" @else rel="prev" @endif
                    >{{ __('ui.jewel_bank.history_previous') }}</a>
                    <span class="text-sm text-steel">{{ __('ui.jewel_bank.history_page', ['current' => $jewelHistory->currentPage(), 'last' => $jewelHistory->lastPage()]) }}</span>
                    <a
                        href="{{ $jewelHistory->nextPageUrl() ?? '#' }}"
                        @class(['button button-ghost', 'pointer-events-none opacity-40' => ! $jewelHistory->hasMorePages()])
                        @if ($jewelHistory->hasMorePages()) rel="next" @else aria-disabled="true" @endif
                    >{{ __('ui.jewel_bank.history_next') }}</a>
                </div>
            @endif
        </section>
    </div>
@endsection
