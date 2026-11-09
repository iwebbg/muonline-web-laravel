@extends('layouts.app')

@section('title', __('ui.admin.moderation_title'))

@section('content')
    <div
        class="shell py-10 lg:py-14"
        x-data="moderationPanel"
        x-on:keydown.escape.window="closeModeration()"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.moderation_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.moderation_intro', ['version' => $currentGameServerVersion]) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span @class([
                    'rounded-full border px-3 py-2 text-xs font-bold uppercase tracking-[0.14em]',
                    'border-emerald-300/25 bg-emerald-300/8 text-emerald-200' => $writesEnabled,
                    'border-amber-300/25 bg-amber-300/8 text-amber-200' => ! $writesEnabled,
                ])>
                    {{ $writesEnabled ? __('ui.admin.moderation_writes_enabled') : __('ui.admin.moderation_writes_disabled') }}
                </span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="button button-ghost">{{ __('ui.navigation.logout') }}</button>
                </form>
            </div>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="moderation" />

            <main class="grid min-w-0 content-start gap-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="GET" action="{{ route('admin.moderation') }}" class="panel-card flex flex-col gap-4 sm:flex-row sm:items-end">
                    <label class="field-group min-w-0 flex-1">
                        <span>{{ __('ui.admin.moderation_search') }}</span>
                        <input
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            maxlength="50"
                            placeholder="{{ __('ui.admin.moderation_search_placeholder') }}"
                        >
                    </label>
                    <button type="submit" class="button button-primary">{{ __('ui.admin.moderation_search_submit') }}</button>
                </form>

                <section id="moderation-targets" class="panel-card overflow-hidden" aria-labelledby="moderation-targets-heading">
                    <div class="border-b border-white/8 p-5 sm:p-6">
                        <p class="kicker">MEMB_INFO + Character</p>
                        <h2 id="moderation-targets-heading" class="mt-2 text-2xl font-semibold text-white">
                            {{ __('ui.admin.moderation_targets') }}
                        </h2>
                        @if ($canModerateCharacters)
                            <p class="mt-2 text-sm text-steel">{{ __('ui.admin.moderation_character_help') }}</p>
                        @endif
                    </div>

                    <div class="grid gap-4 p-4 sm:p-6">
                        @forelse ($accountTargets as $accountTarget)
                            @php($characters = $characterGroups[$accountTarget['accountId']] ?? [])
                            <article class="overflow-hidden rounded-2xl border border-white/8 bg-black/15">
                                <header class="flex flex-col gap-4 border-b border-white/8 bg-white/[0.025] p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                    <div>
                                        <p class="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-steel">{{ __('ui.admin.moderation_account') }}</p>
                                        <h3 class="mt-1 font-semibold text-white">{{ $accountTarget['identifier'] }}</h3>
                                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                            <span class="rounded-full border border-white/10 px-2.5 py-1 text-steel">
                                                {{ $accountTarget['online'] ? __('ui.admin.moderation_online') : __('ui.admin.moderation_offline') }}
                                            </span>
                                            <span @class([
                                                'rounded-full border px-2.5 py-1',
                                                'border-red-300/25 text-red-200' => $accountTarget['blocked'],
                                                'border-emerald-300/25 text-emerald-200' => ! $accountTarget['blocked'],
                                            ])>
                                                {{ $accountTarget['blocked'] ? __('ui.admin.moderation_blocked') : __('ui.admin.moderation_active') }}
                                            </span>
                                        </div>
                                    </div>

                                    @if ($canModerateAccounts)
                                        <button
                                            type="button"
                                            class="button {{ $accountTarget['blocked'] ? 'button-ghost' : 'button-primary' }}"
                                            @disabled(! $writesEnabled)
                                            x-on:click="openModeration('account', @js($accountTarget['identifier']), '{{ $accountTarget['blocked'] ? 'unblock' : 'block' }}')"
                                        >
                                            {{ $accountTarget['blocked'] ? __('ui.admin.moderation_unblock') : __('ui.admin.moderation_block') }}
                                        </button>
                                    @endif
                                </header>

                                @if ($canModerateCharacters)
                                    <div class="divide-y divide-white/8">
                                        @forelse ($characters as $target)
                                            <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                                <div>
                                                    <p class="font-semibold text-white">{{ $target['identifier'] }}</p>
                                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                                        <span @class([
                                                            'rounded-full border px-2.5 py-1',
                                                            'border-red-300/25 text-red-200' => $target['blocked'],
                                                            'border-emerald-300/25 text-emerald-200' => ! $target['blocked'],
                                                            'border-amber-300/25 text-amber-200' => ! in_array($target['controlCode'], [0, 1], true),
                                                        ])>
                                                            @if (! in_array($target['controlCode'], [0, 1], true))
                                                                {{ $protectedControlCodeLabels[$target['controlCode']] ?? __('ui.admin.moderation_protected_code', ['code' => $target['controlCode']]) }}
                                                            @else
                                                                {{ $target['blocked'] ? __('ui.admin.moderation_blocked') : __('ui.admin.moderation_active') }}
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="button {{ $target['blocked'] ? 'button-ghost' : 'button-primary' }}"
                                                    @disabled(! $writesEnabled || $target['online'] || ! in_array($target['controlCode'], [0, 1], true))
                                                    x-on:click="openModeration('character', @js($target['identifier']), '{{ $target['blocked'] ? 'unblock' : 'block' }}')"
                                                >
                                                    {{ $target['blocked'] ? __('ui.admin.moderation_unblock') : __('ui.admin.moderation_block') }}
                                                </button>
                                            </div>
                                        @empty
                                            <p class="p-4 text-sm text-steel sm:px-5">{{ __('ui.admin.moderation_empty_account_characters') }}</p>
                                        @endforelse
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="p-6 text-sm text-steel">{{ __('ui.admin.moderation_empty_targets') }}</p>
                        @endforelse
                    </div>

                    @if ($accountTargets->hasPages())
                        <div class="border-t border-white/8 p-5">{{ $accountTargets->links() }}</div>
                    @endif
                </section>
            </main>
        </div>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="modalOpen"
                class="fixed inset-0 z-[120] grid place-items-center overflow-y-auto bg-black/75 p-4 backdrop-blur-sm"
                x-on:click.self="closeModeration()"
                role="dialog"
                aria-modal="true"
                aria-labelledby="moderation-dialog-title"
            >
                <section class="panel-card w-full max-w-xl p-6 sm:p-8">
                <p class="kicker" x-text="targetType === 'account' ? 'MEMB_INFO' : 'Character'"></p>
                <h2 id="moderation-dialog-title" class="mt-2 text-2xl font-semibold text-white">
                    <span x-text="action === 'block' ? @js(__('ui.admin.moderation_block')) : @js(__('ui.admin.moderation_unblock'))"></span>
                    <span class="text-gold" x-text="targetIdentifier"></span>
                </h2>
                <p class="mt-3 text-sm text-steel">{{ __('ui.admin.moderation_confirmation_help') }}</p>

                <form
                    method="POST"
                    x-bind:action="targetType === 'account' ? @js(route('admin.moderation.accounts')) : @js(route('admin.moderation.characters'))"
                    class="mt-6 grid gap-5"
                >
                    @csrf
                    <input type="hidden" name="targetIdentifier" x-model="targetIdentifier">
                    <input type="hidden" name="action" x-model="action">
                    <input type="hidden" name="idempotencyKey" x-model="idempotencyKey">
                    <label class="field-group">
                        <span>{{ __('ui.admin.moderation_reason') }}</span>
                        <textarea
                            x-ref="moderationReason"
                            name="reason"
                            x-model="reason"
                            required
                            minlength="3"
                            maxlength="500"
                            rows="4"
                            placeholder="{{ __('ui.admin.moderation_reason_placeholder') }}"
                        ></textarea>
                    </label>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" class="button button-ghost" x-on:click="closeModeration()">
                            {{ __('ui.admin.moderation_cancel') }}
                        </button>
                        <button type="submit" class="button button-primary">
                            {{ __('ui.admin.moderation_confirm') }}
                        </button>
                    </div>
                </form>
                </section>
            </div>
        </template>
    </div>
@endsection
