@extends('layouts.app')

@section('title', __('ui.home.title'))

@section('content')
    <section class="shell grid min-h-[42rem] items-center gap-12 py-20 lg:grid-cols-[1.12fr_0.88fr] lg:py-28">
        <div class="max-w-3xl">
            <div class="eyebrow mb-6">
                <span class="status-dot"></span>
                {{ __('ui.home.season') }} · {{ $server['version'] }}
            </div>

            <h1 class="font-display text-5xl leading-[0.95] text-white sm:text-6xl lg:text-8xl">
                {{ __('ui.home.hero_line_one') }}<br>
                <span class="text-gradient">{{ __('ui.home.hero_line_two') }}</span>
            </h1>

            <p class="mt-7 max-w-2xl text-lg leading-8 text-silver sm:text-xl">
                {{ __('ui.home.intro') }}
            </p>

            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ route('account.dashboard') }}" class="button button-primary button-large">{{ __('ui.home.start') }}</a>
                <a href="#rankings" class="button button-ghost button-large">{{ __('ui.home.explore_rankings') }}</a>
            </div>

            <dl class="mt-12 grid max-w-2xl grid-cols-2 gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/10 sm:grid-cols-4">
                <div class="metric-cell">
                    <dt>{{ __('ui.home.experience') }}</dt>
                    <dd>{{ $server['experience'] }}</dd>
                </div>
                <div class="metric-cell">
                    <dt>{{ __('ui.home.drop') }}</dt>
                    <dd>{{ $server['drop'] }}</dd>
                </div>
                <div class="metric-cell">
                    <dt>{{ __('ui.home.accounts') }}</dt>
                    <dd>{{ number_format($server['accounts']) }}</dd>
                </div>
                <div class="metric-cell">
                    <dt>{{ __('ui.home.guilds') }}</dt>
                    <dd>{{ number_format($server['guilds']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="relative mx-auto w-full max-w-lg">
            <div class="hero-orbit" aria-hidden="true"></div>
            <div class="realm-card">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="kicker">{{ __('ui.home.live_realm') }}</p>
                        <h2 class="mt-2 font-display text-2xl text-white">{{ $server['name'] }}</h2>
                        <p class="mt-2 text-xs uppercase tracking-[0.16em] text-steel">
                            {{ __('ui.home.'.$server['data_source'].'_data') }}
                        </p>
                    </div>
                    <div class="online-badge">{{ __('ui.home.online') }}</div>
                </div>

                <div class="mt-10 flex items-end justify-between gap-6">
                    <div>
                        <p class="text-5xl font-semibold tracking-tight text-white">{{ $server['online'] }}</p>
                        <p class="mt-1 text-sm uppercase tracking-[0.2em] text-steel">{{ __('ui.home.warriors_online') }}</p>
                    </div>
                    <p class="text-sm text-steel">{{ __('ui.home.of_capacity', ['capacity' => $server['capacity']]) }}</p>
                </div>

                <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-white/8">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-crimson to-gold shadow-[0_0_24px_rgba(244,174,74,0.5)]"
                        style="width: {{ round(($server['online'] / $server['capacity']) * 100) }}%"
                    ></div>
                </div>

                <div class="mt-10 grid grid-cols-2 gap-3">
                    <div class="glass-stat">
                        <span>{{ __('ui.home.characters') }}</span>
                        <strong>{{ number_format($server['characters']) }}</strong>
                    </div>
                    <div class="glass-stat">
                        <span>{{ __('ui.home.version') }}</span>
                        <strong>{{ $server['version'] }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section
        id="events"
        class="shell scroll-mt-28 py-14"
        x-data="eventSchedule(
            {{ Js::from($events) }},
            {{ Js::from($eventServerNow) }}
        )"
        @keydown.escape.window="closeSchedule"
    >
        <div class="section-heading">
            <div>
                <p class="kicker">{{ __('ui.home.events_kicker') }}</p>
                <h2>{{ __('ui.home.upcoming_events') }}</h2>
            </div>
            <button
                type="button"
                class="button button-ghost"
                :aria-expanded="scheduleOpen"
                aria-controls="full-event-schedule"
                @click="toggleSchedule"
            >
                <span x-text="scheduleOpen
                    ? @js(__('ui.home.hide_schedule'))
                    : @js($additionalEventCount > 0
                        ? __('ui.home.view_more_events', ['count' => $additionalEventCount])
                        : __('ui.home.full_schedule'))"
                >
                    {{ $additionalEventCount > 0
                        ? __('ui.home.view_more_events', ['count' => $additionalEventCount])
                        : __('ui.home.full_schedule') }}
                </span>
            </button>
        </div>

        <div class="mt-8 grid gap-4 lg:grid-cols-3">
            @foreach ($events as $eventIndex => $event)
                <article class="event-card event-card-{{ $event['accent'] }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="kicker">{{ $event['location'] }}</p>
                            <h3 class="mt-2 text-xl font-semibold text-white">{{ $event['name'] }}</h3>
                        </div>
                        <span class="event-rune" aria-hidden="true">✦</span>
                    </div>
                    <div class="mt-10 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-steel">{{ __('ui.home.opens_in') }}</p>
                            <p
                                class="mt-1 font-mono text-3xl text-white"
                                x-text="formattedCountdown(events[{{ $eventIndex }}])"
                            >--:--</p>
                        </div>
                        <span class="text-xs uppercase tracking-[0.18em] text-steel">{{ __('ui.home.live_timer') }}</span>
                    </div>
                    <div class="mt-5 h-1 overflow-hidden rounded-full bg-white/8">
                        <div
                            class="event-progress"
                            :style="`width: ${progressPercent(events[{{ $eventIndex }}])}%`"
                        ></div>
                    </div>
                </article>
            @endforeach
        </div>

        <div
            id="full-event-schedule"
            class="panel-card mt-5"
            x-show="scheduleOpen"
            x-transition
            x-cloak
        >
            <div class="flex flex-col gap-2 border-b border-white/8 pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="kicker">{{ __('ui.home.daily_schedule') }}</p>
                    <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.home.full_schedule') }}</h3>
                </div>
                <p class="text-sm text-steel">
                    {{ __('ui.home.schedule_timezone', ['timezone' => $eventTimezone]) }}
                </p>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                @foreach ($allEvents as $event)
                    <article class="rounded-2xl border border-white/8 bg-black/20 p-4">
                        <p class="kicker">{{ $event['location'] }}</p>
                        <h4 class="mt-2 font-semibold text-white">{{ $event['name'] }}</h4>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-steel">
                            @if ($event['intervalMinutes'] !== null)
                                <span>{{ __('ui.home.every_minutes', ['count' => $event['intervalMinutes']]) }}</span>
                            @elseif ($event['daysOfWeek'] === [])
                                <span>{{ __('ui.home.every_day') }}</span>
                            @else
                                <span>
                                    @foreach ($event['daysOfWeek'] as $dayIndex => $dayOfWeek)
                                        @if ($dayIndex > 0), @endif
                                        {{ __('ui.home.weekdays.'.$dayOfWeek) }}
                                    @endforeach
                                </span>
                            @endif

                            @if ($event['durationMinutes'] > 0)
                                <span>{{ __('ui.home.duration_minutes', ['count' => $event['durationMinutes']]) }}</span>
                            @endif
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($event['dailyTimes'] as $dailyTime)
                                <time class="rounded-lg border border-white/8 bg-white/4 px-2.5 py-1.5 font-mono text-xs text-silver">
                                    {{ $dailyTime }}
                                </time>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="rankings" class="shell scroll-mt-28 py-14" x-data="rankingBoard('players')">
        <div class="section-heading">
            <div>
                <p class="kicker">{{ __('ui.home.rankings_kicker') }}</p>
                <h2>{{ __('ui.home.realm_rankings') }}</h2>
            </div>
            <div class="segmented-control" role="tablist" aria-label="{{ __('ui.home.ranking_category') }}">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'players'"
                    :class="activeTab === 'players' && 'segmented-control-active'"
                    @click="selectTab('players')"
                >{{ __('ui.home.top_players') }}</button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'guilds'"
                    :class="activeTab === 'guilds' && 'segmented-control-active'"
                    @click="selectTab('guilds')"
                >{{ __('ui.home.top_guilds') }}</button>
            </div>
        </div>

        <div class="mt-8 overflow-hidden rounded-3xl border border-white/10 bg-panel/88 shadow-2xl shadow-black/25">
            <div x-show="activeTab === 'players'">
                @foreach ($rankings['players'] as $index => $player)
                    <div class="ranking-row">
                        <div class="ranking-position">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-semibold text-white">{{ $player['name'] }}</p>
                                @if ($player['online'])
                                    <span class="status-dot status-dot-small" title="{{ __('ui.home.online_status') }}"></span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-steel">{{ $player['class'] }}</p>
                        </div>
                        <div class="ranking-value">
                            <strong>{{ $player['resets'] }}</strong>
                            <span>{{ __('ui.home.resets') }}</span>
                        </div>
                        <div class="ranking-value hidden sm:block">
                            <strong>{{ $player['grand_resets'] }}</strong>
                            <span>{{ __('ui.home.grand') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div x-show="activeTab === 'guilds'" x-cloak>
                @foreach ($rankings['guilds'] as $index => $guild)
                    <div class="ranking-row">
                        <div class="ranking-position">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-white">{{ $guild['name'] }}</p>
                            <p class="mt-1 text-sm text-steel">{{ __('ui.home.master', ['name' => $guild['master']]) }}</p>
                        </div>
                        <div class="ranking-value">
                            <strong>{{ number_format($guild['score']) }}</strong>
                            <span>{{ __('ui.home.score') }}</span>
                        </div>
                        <div class="ranking-value hidden sm:block">
                            <strong>{{ $guild['members'] }}</strong>
                            <span>{{ __('ui.home.members') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
