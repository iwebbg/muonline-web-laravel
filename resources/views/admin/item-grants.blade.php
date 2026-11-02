@extends('layouts.app')

@section('title', __('ui.admin.item_grants_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.item_grants_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.item_grants_intro') }}</p>
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

                <section class="panel-card p-5 sm:p-7">
                    <p class="kicker">{{ __('ui.admin.item_grant_search') }}</p>
                    <form method="GET" action="{{ route('admin.item-grants.index') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <label class="field-group flex-1">
                            <span>{{ __('ui.admin.item_grant_search_help') }}</span>
                            <input type="search" name="search" value="{{ $search }}" maxlength="50">
                        </label>
                        <button type="submit" class="button button-ghost self-end">{{ __('ui.admin.item_grant_search') }}</button>
                    </form>
                </section>

                <section
                    class="panel-card p-5 sm:p-7"
                    x-data="{
                        confirmationOpen: false,
                        accountId: @js(old('account_id', $accountTargets[0]->accountId ?? '')),
                        itemCode: @js(old('item_code', $definitions[0]['code'] ?? '')),
                        items: @js($definitionsByCode),
                        selectedItem: @js($definitionsByCode[old('item_code', $definitions[0]['code'] ?? '')] ?? null),
                    }"
                    x-on:keydown.escape.window="confirmationOpen = false"
                >
                    <p class="text-sm text-steel">{{ __('ui.admin.item_grants_safety') }}</p>

                    <form method="POST" action="{{ route('admin.item-grants.store') }}" class="mt-6 grid gap-6" x-ref="grantForm" x-on:submit.prevent="confirmationOpen = true">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                        <input type="hidden" name="ancient_option" value="0">

                        <fieldset>
                            <legend class="kicker">{{ __('ui.admin.item_grant_account') }}</legend>
                            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($accountTargets as $target)
                                    <label class="rounded-2xl border border-white/8 bg-black/20 p-4 text-sm" :class="accountId === @js($target->accountId) ? 'border-gold/40 bg-gold/8' : ''">
                                        <span class="flex items-center justify-between gap-3">
                                            <strong class="text-white">{{ $target->accountId }}</strong>
                                            <span class="{{ $target->online ? 'text-red-200' : 'text-emerald-200' }}">{{ $target->online ? __('ui.admin.moderation_online') : __('ui.admin.moderation_offline') }}</span>
                                        </span>
                                        <input class="sr-only" type="radio" value="{{ $target->accountId }}" x-model="accountId">
                                    </label>
                                @endforeach
                            </div>
                            <label class="field-group mt-4 max-w-md">
                                <span>{{ __('ui.admin.item_grant_account') }}</span>
                                <input type="text" name="account_id" x-model="accountId" minlength="4" maxlength="10" pattern="[A-Za-z0-9_-]+" required>
                            </label>
                        </fieldset>

                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_14rem]">
                            <div class="grid gap-5 md:grid-cols-2">
                                <label class="field-group md:col-span-2">
                                    <span>{{ __('ui.admin.item_grant_item') }}</span>
                                    <select name="item_code" x-model="itemCode" x-on:change="selectedItem = items[itemCode] ?? null" required>
                                        @foreach ($definitions as $definition)
                                            <option value="{{ $definition['code'] }}">{{ $definition['code'] }} · {{ $definition['name'] }} · {{ $definition['width'] }}×{{ $definition['height'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_grant_level') }}</span>
                                    <input type="number" name="level" value="{{ old('level', 0) }}" min="0" max="15" required>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_grant_durability') }}</span>
                                    <input type="number" name="durability" value="{{ old('durability', 255) }}" min="0" max="255" required>
                                </label>
                                <label class="field-group">
                                    <span>{{ __('ui.admin.item_grant_option_level') }}</span>
                                    <input type="number" name="option_level" value="{{ old('option_level', 0) }}" min="0" max="7" required>
                                </label>
                                <input type="hidden" name="excellent_mask" value="0">
                                <label class="flex items-center gap-3 text-sm text-silver">
                                    <input type="checkbox" name="has_skill" value="1" @checked(old('has_skill'))>
                                    {{ __('ui.admin.item_grant_skill') }}
                                </label>
                                <label class="flex items-center gap-3 text-sm text-silver">
                                    <input type="checkbox" name="has_luck" value="1" @checked(old('has_luck'))>
                                    {{ __('ui.admin.item_grant_luck') }}
                                </label>
                            </div>

                            <div class="rounded-2xl border border-white/8 bg-black/20 p-5 text-center">
                                <p class="kicker">{{ __('ui.admin.item_grant_preview') }}</p>
                                <template x-if="selectedItem">
                                    <div class="mt-5">
                                        <img x-show="selectedItem.image" :src="selectedItem.image" :alt="selectedItem.name" class="mx-auto max-h-32 max-w-32 object-contain [image-rendering:pixelated]">
                                        <p class="mt-4 font-semibold text-white" x-text="selectedItem.name"></p>
                                        <p class="mt-1 text-sm text-steel" x-text="`${selectedItem.code} · ${selectedItem.width}×${selectedItem.height}`"></p>
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

                    <div x-cloak x-show="confirmationOpen" class="fixed inset-0 z-[100] grid place-items-center bg-black/80 p-4 backdrop-blur-sm" x-on:click.self="confirmationOpen = false" role="dialog" aria-modal="true">
                        <section class="panel-card w-full max-w-lg p-6 sm:p-8">
                            <p class="kicker">{{ __('ui.admin.item_grant_preview') }}</p>
                            <h2 class="mt-3 text-2xl font-semibold text-white" x-text="selectedItem?.name ?? itemCode"></h2>
                            <p class="mt-3 text-silver"><span x-text="accountId"></span> · {{ __('ui.admin.item_grant_destination') }}</p>
                            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <button type="button" class="button button-ghost" x-on:click="confirmationOpen = false">{{ __('ui.admin.news_cancel') }}</button>
                                <button type="button" class="button button-primary" x-on:click="$refs.grantForm.submit()">{{ __('ui.admin.item_grant_submit') }}</button>
                            </div>
                        </section>
                    </div>
                </section>

                <section class="panel-card overflow-hidden">
                    <div class="border-b border-white/8 p-5 sm:p-6"><p class="kicker">{{ __('ui.admin.item_grant_history') }}</p></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-[0.14em] text-steel">
                                <tr><th class="px-5 py-4">ID</th><th class="px-5 py-4">{{ __('ui.admin.item_grant_account') }}</th><th class="px-5 py-4">{{ __('ui.admin.item_grant_item') }}</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Serial</th></tr>
                            </thead>
                            <tbody class="divide-y divide-white/8">
                                @forelse ($recentOperations as $operation)
                                    <tr><td class="px-5 py-4 text-steel">#{{ $operation->id }}</td><td class="px-5 py-4 text-white">{{ $operation->account_id }}</td><td class="px-5 py-4 text-silver">{{ $operation->item_name }} · {{ $operation->item_code }}</td><td class="px-5 py-4 text-silver">{{ $operation->status->value }}</td><td class="px-5 py-4 text-steel">{{ $operation->serial }}</td></tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.item_grant_no_history') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
@endsection
