@extends('layouts.admin')

@section('title', __('ui.admin.title'))

@section('content')
    <div class="admin-shell py-10 lg:py-14">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.heading') }}</h1>
                <p class="mt-3 max-w-2xl text-silver">{{ __('ui.admin.overview_intro') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="resource-chip">
                    <span>{{ __('ui.admin.services_status') }}</span>
                    <strong class="flex items-center gap-2">
                        <i @class(['status-dot', 'status-dot-server-offline' => ! $systemReady]) aria-hidden="true"></i>
                        {{ $systemReady ? __('ui.admin.service_status_healthy') : __('ui.admin.service_status_degraded') }}
                    </strong>
                </span>
                <span class="resource-chip"><span>{{ __('ui.admin.signed_in_as') }}</span><strong>{{ auth('admin')->user()->name }}</strong></span>
            </div>
        </div>

        <div class="mt-8">
            <main class="grid min-w-0 content-start gap-6">
                @if ($realmAnalytics === null)
                    <section class="panel-card border-red-300/20 py-14 text-center">
                        <p class="kicker">{{ __('ui.admin.analytics_kicker') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('ui.admin.analytics_unavailable') }}</h2>
                    </section>
                @else
                    <section class="panel-card p-5 sm:p-7" aria-labelledby="realm-analytics-heading">
                        <p class="kicker">{{ __('ui.admin.analytics_kicker') }}</p>
                        <h2 id="realm-analytics-heading" class="mt-2 text-2xl font-semibold text-white">
                            {{ __('ui.admin.analytics_heading') }}
                        </h2>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-steel">{{ __('ui.admin.analytics_intro') }}</p>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                            @foreach ([
                                ['label' => __('ui.admin.analytics_accounts'), 'value' => $realmAnalytics['accounts'], 'symbol' => '◎'],
                                ['label' => __('ui.admin.analytics_characters'), 'value' => $realmAnalytics['characters'], 'symbol' => '♙'],
                                ['label' => __('ui.admin.analytics_guilds'), 'value' => $realmAnalytics['guilds'], 'symbol' => '♜'],
                                ['label' => __('ui.admin.analytics_online_players'), 'value' => $realmAnalytics['online_players'], 'symbol' => '●'],
                            ] as $metric)
                                <div class="rounded-2xl border border-white/8 bg-black/20 p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-steel">{{ $metric['label'] }}</p>
                                            <p class="mt-3 font-display text-4xl text-white">{{ number_format($metric['value']) }}</p>
                                        </div>
                                        <span class="grid size-10 place-items-center rounded-xl border border-gold/20 bg-gold/[0.06] text-gold" aria-hidden="true">
                                            {{ $metric['symbol'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <div class="grid items-start gap-6 2xl:grid-cols-2">
                        <section class="panel-card p-5 sm:p-7" aria-labelledby="class-distribution-heading">
                            <p class="kicker">{{ __('ui.admin.analytics_classes_kicker') }}</p>
                            <h2 id="class-distribution-heading" class="mt-2 text-xl font-semibold text-white">
                                {{ __('ui.admin.analytics_classes_heading') }}
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-steel">{{ __('ui.admin.analytics_classes_help', ['version' => $currentGameServerVersion]) }}</p>

                            <div class="mt-6 grid gap-4">
                                @forelse ($realmAnalytics['classes'] as $classEntry)
                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <span class="grid size-8 shrink-0 place-items-center rounded-lg border border-gold/20 bg-gold/[0.06] text-xs font-bold text-gold">
                                                    {{ $classEntry['abbreviation'] }}
                                                </span>
                                                <span class="truncate font-semibold text-silver">{{ $classEntry['label'] }}</span>
                                            </div>
                                            <span class="shrink-0 text-steel">
                                                <strong class="text-white">{{ number_format($classEntry['count']) }}</strong>
                                                · {{ $classEntry['percentage'] }}%
                                            </span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-white/[0.05]">
                                            <div class="h-full rounded-full bg-gradient-to-r from-gold to-orange-500" style="width: {{ $classEntry['percentage'] }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-steel">{{ __('ui.admin.analytics_no_characters') }}</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="panel-card p-5 sm:p-7" aria-labelledby="map-distribution-heading">
                            <p class="kicker">{{ __('ui.admin.analytics_maps_kicker') }}</p>
                            <h2 id="map-distribution-heading" class="mt-2 text-xl font-semibold text-white">
                                {{ __('ui.admin.analytics_maps_heading') }}
                            </h2>
                            <p class="mt-2 text-sm leading-6 text-steel">{{ __('ui.admin.analytics_maps_help') }}</p>

                            <div class="mt-6 grid gap-4">
                                @forelse ($realmAnalytics['maps'] as $mapEntry)
                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                            <span class="truncate font-semibold text-silver">{{ $mapEntry['label'] }}</span>
                                            <span class="shrink-0 text-steel">
                                                <strong class="text-white">{{ number_format($mapEntry['count']) }}</strong>
                                                · {{ $mapEntry['percentage'] }}%
                                            </span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-white/[0.05]">
                                            <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-emerald-300" style="width: {{ $mapEntry['percentage'] }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-steel">{{ __('ui.admin.analytics_no_characters') }}</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @endif
            </main>
        </div>
    </div>
@endsection
