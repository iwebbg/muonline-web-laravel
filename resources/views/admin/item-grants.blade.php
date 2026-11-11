@extends('layouts.admin')

@section('title', __('ui.admin.item_grants_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.item_grants_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.item_grants_intro', ['version' => $currentGameServerVersion]) }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="item-grants" />

            <main class="grid min-w-0 content-start gap-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
                @endif
                @unless ($writesEnabled)
                    <div class="rounded-2xl border border-amber-300/25 bg-amber-300/8 p-4 text-sm text-amber-100">{{ __('ui.admin.item_grants_writes_disabled') }}</div>
                @endunless

                <section
                    class="panel-card p-5 sm:p-7"
                    x-data="itemGrantManager(@js([
                        'searchUrl' => route('admin.item-grants.accounts.index'),
                        'initialAccountId' => old('account_id', $initialAccountId),
                        'items' => $definitionsByCode,
                        'itemLevels' => $itemLevels,
                        'optionLevels' => $optionLevels,
                        'itemOptionLabels' => $itemOptionLabels,
                        'initialItemCode' => old('item_code', $definitions[0]['code'] ?? ''),
                        'initialLevel' => old('level', 0),
                        'initialDurability' => old('durability', 255),
                        'initialOptionLevel' => old('option_level', 0),
                        'initialHasSkill' => old('has_skill', false),
                        'initialHasLuck' => old('has_luck', false),
                        'initialExcellentMask' => old('excellent_mask', 0),
                        'ui' => [
                            'skill' => __('ui.admin.item_grant_skill'),
                            'luck' => __('ui.admin.item_grant_luck'),
                            'itemRequired' => __('ui.admin.item_grant_item_required'),
                        ],
                    ]))"
                    x-on:keydown.escape.window="confirmationOpen = false; closeSuggestions(); closeItemSuggestions()"
                >
                    <p class="text-sm text-steel">{{ __('ui.admin.item_grants_safety') }}</p>

                    <form method="POST" action="{{ route('admin.item-grants.store') }}" class="mt-6 grid gap-6" x-ref="grantForm" x-on:submit.prevent="openConfirmation()">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                        <fieldset>
                            <legend class="kicker">{{ __('ui.admin.item_grant_account') }}</legend>
                            <div class="relative mt-3 max-w-xl" x-on:click.outside="closeSuggestions()">
                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_grant_search_help') }}</span>
                                    <input
                                        type="search"
                                        name="account_id"
                                        x-model="accountId"
                                        x-on:input="scheduleAccountSearch()"
                                        x-on:focus="suggestionsOpen = suggestions.length > 0"
                                        x-on:keydown.down.prevent="moveSuggestion(1)"
                                        x-on:keydown.up.prevent="moveSuggestion(-1)"
                                        x-on:keydown.enter.prevent="selectActiveSuggestion()"
                                        minlength="4"
                                        maxlength="10"
                                        pattern="[A-Za-z0-9_-]+"
                                        autocomplete="off"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-controls="item-grant-account-suggestions"
                                        x-bind:aria-expanded="suggestionsOpen"
                                        x-bind:aria-activedescendant="activeSuggestionIndex >= 0 ? `item-grant-account-${activeSuggestionIndex}` : ''"
                                        placeholder="{{ __('ui.admin.item_grant_search_placeholder') }}"
                                        required
                                    >
                                </label>

                                <div
                                    id="item-grant-account-suggestions"
                                    class="absolute inset-x-0 top-full z-40 mt-2 overflow-hidden rounded-2xl border border-white/10 bg-[#0d1016]/98 shadow-2xl backdrop-blur-xl"
                                    x-cloak
                                    x-show="suggestionsOpen"
                                    role="listbox"
                                >
                                    <p x-show="searchLoading" class="px-4 py-3 text-sm text-steel">{{ __('ui.admin.item_grant_search_loading') }}</p>
                                    <p x-show="searchFailed" class="px-4 py-3 text-sm text-red-200">{{ __('ui.admin.item_grant_search_failed') }}</p>
                                    <p x-show="!searchLoading && !searchFailed && suggestions.length === 0" class="px-4 py-3 text-sm text-steel">{{ __('ui.admin.item_grant_search_empty') }}</p>
                                    <template x-for="(account, index) in suggestions" :key="account.account_id">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left text-sm transition hover:bg-white/[0.06]"
                                            x-bind:id="`item-grant-account-${index}`"
                                            x-bind:class="activeSuggestionIndex === index ? 'bg-white/[0.08]' : ''"
                                            x-on:mouseenter="activeSuggestionIndex = index"
                                            x-on:click="selectAccount(account)"
                                            role="option"
                                            x-bind:aria-selected="selectedAccount?.account_id === account.account_id"
                                        >
                                            <strong class="text-white" x-text="account.account_id"></strong>
                                            <span x-bind:class="account.online ? 'text-red-200' : 'text-emerald-200'" x-text="account.online ? @js(__('ui.admin.moderation_online')) : @js(__('ui.admin.moderation_offline'))"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-steel">{{ __('ui.admin.item_grant_search_minimum') }}</p>
                        </fieldset>

                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_14rem]">
                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="relative md:col-span-2" x-on:click.outside="closeItemSuggestions()">
                                    <input type="hidden" name="item_code" x-bind:value="itemCode">
                                    <label class="field-group">
                                        <span>{{ __('ui.admin.item_grant_item') }}</span>
                                        <input
                                            type="search"
                                            x-ref="itemSearch"
                                            x-model="itemSearch"
                                            x-on:input="filterItems()"
                                            x-on:focus="itemSuggestionsOpen = true"
                                            x-on:keydown.down.prevent="moveItemSuggestion(1)"
                                            x-on:keydown.up.prevent="moveItemSuggestion(-1)"
                                            x-on:keydown.enter.prevent="selectActiveItemSuggestion()"
                                            autocomplete="off"
                                            role="combobox"
                                            aria-autocomplete="list"
                                            aria-controls="item-grant-item-suggestions"
                                            x-bind:aria-expanded="itemSuggestionsOpen"
                                            x-bind:aria-activedescendant="activeItemSuggestionIndex >= 0 ? `item-grant-item-${activeItemSuggestionIndex}` : ''"
                                            placeholder="{{ __('ui.admin.item_grant_item_placeholder') }}"
                                            required
                                        >
                                    </label>

                                    <div
                                        id="item-grant-item-suggestions"
                                        class="absolute inset-x-0 top-full z-30 mt-2 max-h-80 overflow-y-auto rounded-2xl border border-white/10 bg-[#0d1016]/98 shadow-2xl backdrop-blur-xl"
                                        x-cloak
                                        x-show="itemSuggestionsOpen"
                                        role="listbox"
                                    >
                                        <p x-show="itemSuggestions.length === 0" class="px-4 py-3 text-sm text-steel">{{ __('ui.admin.item_grant_item_empty') }}</p>
                                        <template x-for="(item, index) in itemSuggestions" :key="item.code">
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-4 px-4 py-3 text-left transition hover:bg-white/[0.06]"
                                                x-bind:id="`item-grant-item-${index}`"
                                                x-bind:class="activeItemSuggestionIndex === index ? 'bg-white/[0.08]' : ''"
                                                x-on:mouseenter="activeItemSuggestionIndex = index"
                                                x-on:click="selectItem(item)"
                                                role="option"
                                                x-bind:aria-selected="itemCode === item.code"
                                            >
                                                <span class="grid size-11 shrink-0 place-items-center rounded-xl border border-white/8 bg-black/25">
                                                    <img x-show="item.image" x-bind:src="item.image" alt="" class="max-h-9 max-w-9 object-contain [image-rendering:pixelated]">
                                                </span>
                                                <strong class="text-sm text-white" x-text="item.name"></strong>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_grant_level') }}</span>
                                    <select name="level" x-model.number="level" required>
                                        <template x-for="itemLevel in itemLevels" :key="itemLevel">
                                            <option x-bind:value="itemLevel" x-text="itemLevel === 0 ? '0' : `+${itemLevel}`"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_grant_durability') }}</span>
                                    <input type="number" name="durability" x-model.number="durability" min="0" max="255" required>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_grant_option_level') }}</span>
                                    <select name="option_level" x-model.number="optionLevel" required>
                                        <template x-for="option in additionalOptionChoices" :key="option.value">
                                            <option x-bind:value="option.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </label>
                                <input type="hidden" name="ancient_option" value="0">
                                <input type="hidden" name="excellent_mask" x-bind:value="excellentMask">
                                <label class="flex items-center gap-3 text-sm text-silver">
                                    <input type="checkbox" name="has_skill" value="1" x-model="hasSkill">
                                    {{ __('ui.admin.item_grant_skill') }}
                                </label>
                                <label class="flex items-center gap-3 text-sm text-silver">
                                    <input type="checkbox" name="has_luck" value="1" x-model="hasLuck">
                                    {{ __('ui.admin.item_grant_luck') }}
                                </label>

                                <fieldset class="grid gap-3 md:col-span-2">
                                    <legend class="text-sm font-semibold text-white">{{ __('ui.admin.item_grant_excellent_options') }}</legend>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <template x-for="option in excellentOptions" :key="option.bit">
                                            <label class="flex items-start gap-3 rounded-xl border border-white/8 bg-black/15 p-3 text-sm text-silver">
                                                <input type="checkbox" x-model.number="excellentOptionBits" x-bind:value="option.bit">
                                                <span x-text="option.label"></span>
                                            </label>
                                        </template>
                                    </div>
                                </fieldset>
                            </div>

                            <div class="rounded-2xl border border-white/8 bg-black/20 p-5 text-center">
                                <p class="kicker">{{ __('ui.admin.item_grant_preview') }}</p>
                                <template x-if="selectedItem">
                                    <div class="mt-5">
                                        <img x-show="selectedItem.image" :src="selectedItem.image" :alt="selectedItem.name" class="mx-auto max-h-32 max-w-32 object-contain [image-rendering:pixelated]">
                                        <p class="mt-4 font-semibold text-white" x-text="itemTitle"></p>
                                        <p class="mt-2 text-xs text-steel" x-text="`${@js(__('ui.admin.item_grant_durability'))}: ${durability}`"></p>
                                        <ul x-show="fullItemOptions.length > 0" class="mt-4 grid gap-1 text-left text-xs text-emerald-200">
                                            <template x-for="option in fullItemOptions" :key="option">
                                                <li x-text="option"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <label class="field-group">
                            <span>{{ __('ui.admin.item_grant_reason') }}</span>
                            <textarea name="reason" rows="3" minlength="5" maxlength="500" required>{{ old('reason') }}</textarea>
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="button button-primary" @disabled(! $writesEnabled)>{{ __('ui.admin.item_grant_submit') }}</button>
                        </div>
                    </form>

                    <template x-teleport="body">
                        <div x-cloak x-show="confirmationOpen" class="fixed inset-0 z-[120] grid place-items-center bg-black/80 p-4 backdrop-blur-sm" x-on:click.self="confirmationOpen = false" role="dialog" aria-modal="true">
                            <section class="panel-card w-full max-w-lg p-6 sm:p-8">
                                <p class="kicker">{{ __('ui.admin.item_grant_preview') }}</p>
                                <h2 class="mt-3 text-2xl font-semibold text-white" x-text="itemTitle"></h2>
                                <p class="mt-3 text-silver"><span x-text="accountId"></span> · {{ __('ui.admin.item_grant_destination') }}</p>
                                <ul x-show="fullItemOptions.length > 0" class="mt-4 grid gap-1 text-sm text-emerald-200">
                                    <template x-for="option in fullItemOptions" :key="option">
                                        <li x-text="option"></li>
                                    </template>
                                </ul>
                                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <button type="button" class="button button-ghost" x-on:click="confirmationOpen = false">{{ __('ui.admin.news_cancel') }}</button>
                                    <button type="button" class="button button-primary" x-on:click="$refs.grantForm.submit()">{{ __('ui.admin.item_grant_submit') }}</button>
                                </div>
                            </section>
                        </div>
                    </template>
                </section>

                <section class="panel-card overflow-hidden">
                    <div class="border-b border-white/8 p-5 sm:p-6">
                        <p class="kicker">{{ __('ui.admin.item_grant_history') }}</p>
                        <form method="GET" action="{{ route('admin.item-grants.index') }}" class="mt-5 grid gap-3 md:grid-cols-[minmax(16rem,1fr)_13rem_auto] md:items-end">
                            <label class="field-group">
                                <span>{{ __('ui.admin.item_grant_history_search') }}</span>
                                <input
                                    type="search"
                                    name="history_search"
                                    value="{{ $historyFilters['search'] }}"
                                    maxlength="120"
                                    placeholder="{{ __('ui.admin.item_grant_history_search_placeholder') }}"
                                >
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.item_grant_history_status') }}</span>
                                <select name="history_status">
                                    <option value="">{{ __('ui.admin.item_grant_history_all_statuses') }}</option>
                                    @foreach (['pending', 'completed', 'failed'] as $status)
                                        <option value="{{ $status }}" @selected($historyFilters['status'] === $status)>
                                            {{ __('ui.admin.item_grant_statuses.'.$status) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="button button-primary">{{ __('ui.admin.item_grant_history_filter') }}</button>
                                <a href="{{ route('admin.item-grants.index') }}" class="button button-ghost">{{ __('ui.admin.item_grant_history_clear') }}</a>
                            </div>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                                <tr><th class="px-5 py-4">ID</th><th class="px-5 py-4">{{ __('ui.admin.item_grant_account') }}</th><th class="px-5 py-4">{{ __('ui.admin.item_grant_item') }}</th><th class="px-5 py-4">{{ __('ui.admin.item_grant_reason') }}</th><th class="px-5 py-4">{{ __('ui.admin.item_grant_history_status') }}</th><th class="px-5 py-4">Serial</th></tr>
                            </thead>
                            <tbody class="divide-y divide-white/8">
                                @forelse ($operations as $operation)
                                    <tr>
                                        <td class="px-5 py-4 text-steel">#{{ $operation->id }}</td>
                                        <td class="px-5 py-4 text-white">{{ $operation->account_id }}</td>
                                        <td class="px-5 py-4 text-silver">{{ $operation->item_name }}</td>
                                        <td class="max-w-md whitespace-normal break-words px-5 py-4 text-silver">{{ $operation->reason }}</td>
                                        <td class="px-5 py-4">
                                            <span @class([
                                                'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                                'border-amber-300/25 bg-amber-300/8 text-amber-100' => $operation->status->value === 'pending',
                                                'border-emerald-300/25 bg-emerald-300/8 text-emerald-100' => $operation->status->value === 'completed',
                                                'border-red-300/25 bg-red-300/8 text-red-100' => $operation->status->value === 'failed',
                                            ])>
                                                {{ __('ui.admin.item_grant_statuses.'.$operation->status->value) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-steel">{{ $operation->serial ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.item_grant_no_history') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($operations->hasPages())
                        <div class="border-t border-white/8 p-5">{{ $operations->links() }}</div>
                    @endif
                </section>
            </main>
        </div>
    </div>
@endsection
