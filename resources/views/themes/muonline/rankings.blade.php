@extends('layouts.app')

@section('title', __('ui.rankings.title'))

@section('content')
    <section class="relative overflow-hidden border-b border-white/8">
        <div class="absolute inset-0 bg-[url('/images/backgrounds/default.jpg')] bg-cover bg-center opacity-25"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-ink/35 via-ink/80 to-ink"></div>
        <div class="shell relative py-14 sm:py-20">
            <p class="eyebrow">{{ __('ui.rankings.eyebrow') }}</p>
            <h1 class="mt-4 max-w-4xl font-display text-5xl text-white sm:text-6xl">{{ __('ui.rankings.heading') }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-silver">{{ __('ui.rankings.intro') }}</p>
        </div>
    </section>

    <section
        class="shell relative py-10 lg:py-14"
        x-data="rankingExplorer(@js($rankingPage), @js($rankingDataUrl))"
        x-on:ranking-refresh.window="loadPage(pageData.meta.current_page)"
    >
        <div class="panel-card p-4 sm:p-5">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="eyebrow">{{ __('ui.rankings.choose_board') }}</p>
                    <div class="mt-3 inline-flex w-full rounded-2xl border border-white/8 bg-black/20 p-1 sm:w-auto" role="tablist" aria-label="{{ __('ui.rankings.categories') }}">
                        @foreach ($rankingBoards as $board)
                            <button
                                type="button"
                                class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold transition sm:flex-none"
                                x-bind:class="board === '{{ $board }}' ? 'bg-white/10 text-white shadow-lg' : 'text-steel hover:text-white'"
                                x-on:click="selectBoard('{{ $board }}')"
                                x-bind:aria-selected="board === '{{ $board }}'"
                                role="tab"
                            >
                                {{ __('ui.rankings.'.$board) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="field-group min-w-0 sm:w-80">
                        <span>{{ __('ui.rankings.search') }}</span>
                        <input
                            type="search"
                            x-model="search"
                            x-on:input="scheduleSearch()"
                            maxlength="50"
                            placeholder="{{ __('ui.rankings.search_placeholder') }}"
                        >
                    </label>
                    <label
                        class="flex min-h-12 items-center gap-3 rounded-xl border border-white/10 bg-black/20 px-4 text-sm text-silver"
                        x-show="['overall', 'pk'].includes(board)"
                    >
                        <input
                            type="checkbox"
                            class="size-4 accent-amber-400"
                            x-model="onlineOnly"
                            x-on:change="toggleOnline()"
                        >
                        {{ __('ui.rankings.online_only') }}
                    </label>
                </div>
            </div>

            <div class="mt-5 border-t border-white/8 pt-5" x-show="['overall', 'pk'].includes(board)">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-steel">{{ __('ui.rankings.filter_by_class') }}</p>
                <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="{{ __('ui.rankings.filter_by_class') }}">
                    <button
                        type="button"
                        class="rounded-xl border px-4 py-2 text-xs font-bold transition"
                        x-bind:class="classFilter === '' ? 'border-gold/50 bg-gold/10 text-gold' : 'border-white/10 text-steel hover:border-white/25 hover:text-white'"
                        x-on:click="selectClass('')"
                    >
                        {{ __('ui.rankings.all_classes') }}
                    </button>
                    @foreach ($classFilters as $classFilter)
                        <button
                            type="button"
                            class="group flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold transition"
                            x-bind:class="classFilter === '{{ $classFilter['value'] }}' ? 'border-gold/50 bg-gold/10 text-gold' : 'border-white/10 text-steel hover:border-white/25 hover:text-white'"
                            x-on:click="selectClass('{{ $classFilter['value'] }}')"
                            title="{{ $classFilter['name'] }}"
                        >
                            <span class="rounded-md bg-white/[0.06] px-1.5 py-0.5 text-[0.68rem] text-white">{{ $classFilter['label'] }}</span>
                            <span class="hidden sm:inline">{{ $classFilter['name'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow" x-text="board === 'castle_siege' ? @js(__('ui.rankings.castle_siege_owner')) : (board === 'guilds' ? @js(__('ui.rankings.guilds')) : (board === 'gens' ? @js(__('ui.rankings.gens_standings')) : (board === 'pk' ? @js(__('ui.rankings.top_pk')) : @js(__('ui.rankings.overall')))))"></p>
                <h2 class="mt-2 font-display text-4xl text-white">{{ __('ui.rankings.leaderboard') }}</h2>
            </div>
            <p class="text-sm text-steel">
                <span x-text="pageData.meta.from"></span>–<span x-text="pageData.meta.to"></span>
                {{ __('ui.rankings.of') }}
                <strong class="text-white" x-text="pageData.meta.total"></strong>
            </p>
        </div>

        <div class="relative mt-6 min-h-80" aria-live="polite" x-bind:aria-busy="loading">
            <div
                class="absolute inset-0 z-30 flex items-start justify-center rounded-3xl bg-ink/75 pt-28 backdrop-blur-sm"
                x-cloak
                x-show="loading"
            >
                <span class="rounded-full border border-gold/30 bg-black/40 px-5 py-3 text-sm font-semibold text-gold">{{ __('ui.rankings.loading') }}</span>
            </div>

            <div class="panel-card border-red-400/20 p-8 text-center" x-cloak x-show="failed">
                <p class="text-red-200">{{ __('ui.rankings.error') }}</p>
                <button type="button" class="button button-ghost mt-4" x-on:click="loadPage(pageData.meta.current_page)">{{ __('ui.rankings.retry') }}</button>
            </div>

            <div class="grid gap-5 lg:grid-cols-3" x-show="!failed && podiumItems.length > 0">
                <template x-for="entry in podiumItems" x-bind:key="`${board}-${entry.rank}-${entry.name}`">
                    <article class="group relative overflow-hidden rounded-[1.7rem] border border-white/10 bg-[#0e1117] shadow-2xl transition duration-300 hover:-translate-y-1 hover:border-gold/35">
                        <template x-if="!['guilds', 'castle_siege'].includes(board)">
                            <a x-bind:href="entry.profile_url" class="block">
                                <div class="relative h-72 overflow-hidden sm:h-80">
                                    <img x-bind:src="entry.image" alt="" class="size-full object-cover object-top transition duration-500 group-hover:scale-[1.025]">
                                    <div class="absolute inset-0 bg-gradient-to-t from-[#0e1117] via-transparent to-transparent"></div>
                                    <span class="absolute left-4 top-4 grid size-12 place-items-center rounded-2xl border border-gold/40 bg-black/70 font-display text-xl text-gold backdrop-blur" x-text="String(entry.rank).padStart(2, '0')"></span>
                                </div>
                                <div class="p-5">
                                    <p class="eyebrow" x-text="entry.class"></p>
                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        <h3 class="font-display text-3xl text-white" x-text="entry.name"></h3>
                                        <span class="size-2.5 rounded-full" x-bind:class="entry.online ? 'bg-emerald-300 shadow-[0_0_14px_rgba(110,231,183,.8)]' : 'bg-steel/50'"></span>
                                    </div>
                                    <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                                        <template x-if="board === 'overall'">
                                            <div class="contents">
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.level"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.profiles.level') }}</span></div>
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.resets"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.home.resets') }}</span></div>
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.grand_resets"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.home.grand') }}</span></div>
                                            </div>
                                        </template>
                                        <template x-if="board === 'pk'">
                                            <div class="contents">
                                                <div class="rounded-xl border border-red-300/15 bg-red-400/[0.04] p-3"><strong class="block text-red-200" x-text="entry.pk_count"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.pk_kills') }}</span></div>
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.pk_level"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.pk_level') }}</span></div>
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.resets"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.home.resets') }}</span></div>
                                            </div>
                                        </template>
                                        <template x-if="board === 'gens'">
                                            <div class="contents">
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.contribution.toLocaleString()"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.contribution') }}</span></div>
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.family_name"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.account.gens_family') }}</span></div>
                                                <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.family_rank > 0 ? `#${entry.family_rank.toLocaleString()}` : @js(__('ui.account.gens_unranked'))"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.family_rank') }}</span></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </a>
                        </template>

                        <template x-if="['guilds', 'castle_siege'].includes(board)">
                            <a x-bind:href="entry.profile_url" class="flex min-h-[27rem] flex-col items-center justify-center p-8 text-center">
                                <span class="absolute left-4 top-4 grid size-12 place-items-center rounded-2xl border border-gold/40 bg-black/50 font-display text-xl text-gold" x-text="String(entry.rank).padStart(2, '0')"></span>
                                <span class="guild-mark scale-[2.5]" aria-hidden="true">
                                    <template x-for="(color, pixelIndex) in entry.mark_colors" x-bind:key="pixelIndex">
                                        <span class="guild-mark-pixel" x-bind:style="`background-color: ${color}`"></span>
                                    </template>
                                </span>
                                <p class="eyebrow mt-16" x-text="board === 'castle_siege' ? @js(__('ui.rankings.castle_siege_owner')) : @js(__('ui.rankings.guild'))"></p>
                                <h3 class="mt-2 font-display text-4xl text-white" x-text="entry.name"></h3>
                                <p class="mt-2 text-sm text-silver"><span>{{ __('ui.rankings.master') }} · </span><strong class="text-white" x-text="entry.master"></strong></p>
                                <div class="mt-6 grid w-full grid-cols-2 gap-3">
                                    <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.score"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.score') }}</span></div>
                                    <div class="rounded-xl border border-white/8 bg-white/[0.025] p-3"><strong class="block text-white" x-text="entry.members"></strong><span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.members') }}</span></div>
                                </div>
                            </a>
                        </template>
                    </article>
                </template>
            </div>

            <div class="mt-5 grid gap-3" x-show="!failed && listItems.length > 0">
                <template x-for="entry in listItems" x-bind:key="`${board}-${entry.rank}-${entry.name}`">
                    <a x-bind:href="entry.profile_url" class="group grid items-center gap-4 rounded-2xl border border-white/8 bg-[#0e1117]/90 p-4 transition hover:border-gold/30 hover:bg-white/[0.035] sm:grid-cols-[4rem_1fr_auto] sm:px-5">
                        <span class="grid size-12 place-items-center rounded-xl border border-gold/25 bg-gold/[0.06] font-display text-lg text-gold" x-text="String(entry.rank).padStart(2, '0')"></span>
                        <div class="flex min-w-0 items-center gap-4">
                            <template x-if="!['guilds', 'castle_siege'].includes(board)">
                                <img x-bind:src="entry.image" alt="" class="size-14 rounded-xl object-cover object-top">
                            </template>
                            <template x-if="['guilds', 'castle_siege'].includes(board)">
                                <span class="guild-mark shrink-0" aria-hidden="true">
                                    <template x-for="(color, pixelIndex) in entry.mark_colors" x-bind:key="pixelIndex">
                                        <span class="guild-mark-pixel" x-bind:style="`background-color: ${color}`"></span>
                                    </template>
                                </span>
                            </template>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="truncate font-semibold text-white transition group-hover:text-gold" x-text="entry.name"></h3>
                                    <span x-show="!['guilds', 'castle_siege'].includes(board)" class="rounded-md border border-white/10 bg-white/[0.04] px-1.5 py-0.5 text-[0.65rem] font-bold text-silver" x-text="entry.class_abbreviation"></span>
                                </div>
                                <p class="mt-1 truncate text-sm text-steel" x-text="['guilds', 'castle_siege'].includes(board) ? `${@js(__('ui.rankings.master'))}: ${entry.master}` : entry.class"></p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-5 sm:justify-end">
                            <template x-if="board === 'overall'">
                                <div class="contents">
                                    <p class="text-right"><strong class="block text-white" x-text="entry.level"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.profiles.level') }}</span></p>
                                    <p class="text-right"><strong class="block text-white" x-text="entry.resets"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.home.resets') }}</span></p>
                                    <p class="text-right"><strong class="block text-white" x-text="entry.grand_resets"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.home.grand') }}</span></p>
                                </div>
                            </template>
                            <template x-if="board === 'pk'">
                                <div class="contents">
                                    <p class="text-right"><strong class="block text-red-200" x-text="entry.pk_count"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.pk_kills') }}</span></p>
                                    <p class="text-right"><strong class="block text-white" x-text="entry.pk_level"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.pk_level') }}</span></p>
                                </div>
                            </template>
                            <template x-if="board === 'gens'">
                                <div class="contents">
                                    <p class="text-right"><strong class="block text-white" x-text="entry.contribution.toLocaleString()"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.contribution') }}</span></p>
                                    <p class="text-right"><strong class="block text-white" x-text="entry.family_name"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.account.gens_family') }}</span></p>
                                    <p class="text-right"><strong class="block text-white" x-text="entry.family_rank > 0 ? `#${entry.family_rank.toLocaleString()}` : @js(__('ui.account.gens_unranked'))"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.family_rank') }}</span></p>
                                </div>
                            </template>
                            <template x-if="['guilds', 'castle_siege'].includes(board)">
                                <div class="contents">
                                    <p class="text-right"><strong class="block text-white" x-text="entry.score"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.score') }}</span></p>
                                    <p class="text-right"><strong class="block text-white" x-text="entry.members"></strong><span class="text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.rankings.members') }}</span></p>
                                </div>
                            </template>
                        </div>
                    </a>
                </template>
            </div>

            <div class="panel-card p-10 text-center text-steel" x-show="!failed && pageData.items.length === 0">
                <p x-text="board === 'castle_siege' ? @js(__('ui.rankings.no_castle_owner')) : (board === 'guilds' ? @js(__('ui.rankings.no_guilds')) : (board === 'gens' ? @js(__('ui.rankings.no_gens_members')) : @js(__('ui.rankings.no_players'))))"></p>
            </div>
        </div>

        <nav class="mt-7 flex flex-wrap items-center justify-center gap-2" x-show="pageData.meta.last_page > 1" aria-label="{{ __('ui.rankings.pagination') }}">
            <button type="button" class="button button-ghost px-4" x-bind:disabled="pageData.meta.current_page === 1 || loading" x-on:click="loadPage(pageData.meta.current_page - 1)">{{ __('ui.rankings.previous') }}</button>
            <template x-for="page in pageNumbers" x-bind:key="page">
                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-xl border text-sm font-semibold transition"
                    x-bind:class="page === pageData.meta.current_page ? 'border-gold/50 bg-gold/10 text-gold' : 'border-white/10 text-steel hover:text-white'"
                    x-bind:aria-current="page === pageData.meta.current_page ? 'page' : null"
                    x-on:click="loadPage(page)"
                    x-text="page"
                ></button>
            </template>
            <button type="button" class="button button-ghost px-4" x-bind:disabled="pageData.meta.current_page === pageData.meta.last_page || loading" x-on:click="loadPage(pageData.meta.current_page + 1)">{{ __('ui.rankings.next') }}</button>
        </nav>
        <p class="mt-3 text-center text-xs uppercase tracking-widest text-steel" x-show="pageData.meta.last_page > 1">
            {{ __('ui.rankings.page') }} <span x-text="pageData.meta.current_page"></span> {{ __('ui.rankings.of') }} <span x-text="pageData.meta.last_page"></span>
        </p>
    </section>
@endsection
