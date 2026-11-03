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
        class="shell py-10 lg:py-14"
        x-data="rankingExplorer(@js($rankings))"
    >
        <div class="panel-card p-4 sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="inline-flex w-full rounded-2xl border border-white/8 bg-black/20 p-1 sm:w-auto" role="tablist" aria-label="{{ __('ui.rankings.categories') }}">
                    <button
                        type="button"
                        class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold transition sm:flex-none"
                        x-bind:class="activeTab === 'players' ? 'bg-white/10 text-white shadow-lg' : 'text-steel hover:text-white'"
                        x-on:click="selectTab('players')"
                        x-bind:aria-selected="activeTab === 'players'"
                        role="tab"
                    >
                        {{ __('ui.rankings.players') }}
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold transition sm:flex-none"
                        x-bind:class="activeTab === 'guilds' ? 'bg-white/10 text-white shadow-lg' : 'text-steel hover:text-white'"
                        x-on:click="selectTab('guilds')"
                        x-bind:aria-selected="activeTab === 'guilds'"
                        role="tab"
                    >
                        {{ __('ui.rankings.guilds') }}
                    </button>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="field-group min-w-0 sm:w-80">
                        <span>{{ __('ui.rankings.search') }}</span>
                        <input
                            type="search"
                            x-model.debounce.150ms="search"
                            x-on:input="resetDisplayLimit()"
                            maxlength="50"
                            placeholder="{{ __('ui.rankings.search_placeholder') }}"
                        >
                    </label>
                    <label
                        class="flex min-h-12 items-center gap-3 rounded-xl border border-white/10 bg-black/20 px-4 text-sm text-silver"
                        x-show="activeTab === 'players'"
                    >
                        <input
                            type="checkbox"
                            class="size-4 accent-amber-400"
                            x-model="onlineOnly"
                            x-on:change="resetDisplayLimit()"
                        >
                        {{ __('ui.rankings.online_only') }}
                    </label>
                </div>
            </div>
        </div>

        <div class="mt-6 panel-card overflow-hidden" x-show="activeTab === 'players'" role="tabpanel">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-left">
                    <thead class="border-b border-white/8 bg-white/[0.025] text-xs uppercase tracking-[0.16em] text-steel">
                        <tr>
                            <th class="px-5 py-4 sm:px-6">{{ __('ui.rankings.rank') }}</th>
                            <th class="px-5 py-4 sm:px-6">{{ __('ui.rankings.character') }}</th>
                            <th class="px-5 py-4 sm:px-6">{{ __('ui.rankings.class') }}</th>
                            <th class="px-5 py-4 text-right sm:px-6">{{ __('ui.home.resets') }}</th>
                            <th class="px-5 py-4 text-right sm:px-6">{{ __('ui.home.grand') }}</th>
                            <th class="px-5 py-4 text-right sm:px-6">{{ __('ui.rankings.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/8">
                        <template x-for="player in visiblePlayers" x-bind:key="player.name">
                            <tr class="transition hover:bg-white/[0.025]">
                                <td class="px-5 py-4 font-display text-xl text-gold sm:px-6" x-text="`#${player.rank}`"></td>
                                <td class="px-5 py-4 font-semibold text-white sm:px-6">
                                    <a x-bind:href="player.profile_url" class="flex items-center gap-3 transition hover:text-gold">
                                        <img x-bind:src="player.image" alt="" class="size-10 rounded-lg object-cover object-top" loading="lazy">
                                        <span x-text="player.name"></span>
                                    </a>
                                </td>
                                <td class="px-5 py-4 text-silver sm:px-6" x-text="player.class"></td>
                                <td class="px-5 py-4 text-right font-semibold text-white sm:px-6" x-text="player.resets"></td>
                                <td class="px-5 py-4 text-right font-semibold text-white sm:px-6" x-text="player.grand_resets"></td>
                                <td class="px-5 py-4 text-right sm:px-6">
                                    <span
                                        class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        x-bind:class="player.online ? 'border-emerald-300/25 text-emerald-200' : 'border-white/10 text-steel'"
                                    >
                                        <span class="size-1.5 rounded-full" x-bind:class="player.online ? 'bg-emerald-300' : 'bg-steel'"></span>
                                        <span x-text="player.online ? @js(__('ui.rankings.online')) : @js(__('ui.rankings.offline'))"></span>
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div x-show="filteredPlayers.length === 0" class="p-10 text-center text-steel">
                {{ __('ui.rankings.no_players') }}
            </div>
            <div x-show="visiblePlayers.length < filteredPlayers.length" class="border-t border-white/8 p-5 text-center">
                <button type="button" class="button button-ghost" x-on:click="showMore()">
                    {{ __('ui.rankings.show_more') }}
                </button>
            </div>
        </div>

        <div class="mt-6 panel-card overflow-hidden" x-cloak x-show="activeTab === 'guilds'" role="tabpanel">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-left">
                    <thead class="border-b border-white/8 bg-white/[0.025] text-xs uppercase tracking-[0.16em] text-steel">
                        <tr>
                            <th class="px-5 py-4 sm:px-6">{{ __('ui.rankings.rank') }}</th>
                            <th class="px-5 py-4 sm:px-6">{{ __('ui.rankings.guild') }}</th>
                            <th class="px-5 py-4 sm:px-6">{{ __('ui.rankings.master') }}</th>
                            <th class="px-5 py-4 text-right sm:px-6">{{ __('ui.rankings.score') }}</th>
                            <th class="px-5 py-4 text-right sm:px-6">{{ __('ui.rankings.members') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/8">
                        <template x-for="guild in visibleGuilds" x-bind:key="guild.name">
                            <tr class="transition hover:bg-white/[0.025]">
                                <td class="px-5 py-4 font-display text-xl text-gold sm:px-6" x-text="`#${guild.rank}`"></td>
                                <td class="px-5 py-4 font-semibold text-white sm:px-6">
                                    <a x-bind:href="guild.profile_url" class="flex items-center gap-3 transition hover:text-gold">
                                        <span class="guild-mark" aria-hidden="true">
                                            <template x-for="(color, pixelIndex) in guild.mark_colors" x-bind:key="pixelIndex">
                                                <span class="guild-mark-pixel" x-bind:style="`background-color: ${color}`"></span>
                                            </template>
                                        </span>
                                        <span x-text="guild.name"></span>
                                    </a>
                                </td>
                                <td class="px-5 py-4 text-silver sm:px-6" x-text="guild.master"></td>
                                <td class="px-5 py-4 text-right font-semibold text-white sm:px-6" x-text="guild.score"></td>
                                <td class="px-5 py-4 text-right font-semibold text-white sm:px-6" x-text="guild.members"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div x-show="filteredGuilds.length === 0" class="p-10 text-center text-steel">
                {{ __('ui.rankings.no_guilds') }}
            </div>
            <div x-show="visibleGuilds.length < filteredGuilds.length" class="border-t border-white/8 p-5 text-center">
                <button type="button" class="button button-ghost" x-on:click="showMore()">
                    {{ __('ui.rankings.show_more') }}
                </button>
            </div>
        </div>
    </section>
@endsection
