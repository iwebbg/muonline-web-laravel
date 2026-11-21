@props(['events', 'showSchedule' => false])

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
            @if ($showSchedule)
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-steel">
                    @if ($event['cycleDays'] !== null)
                        <span>{{ __('ui.home.every_days', ['count' => $event['cycleDays']]) }}</span>
                    @elseif ($event['intervalMinutes'] !== null)
                        <span>{{ __('ui.home.every_minutes', ['count' => $event['intervalMinutes']]) }}</span>
                    @elseif (($event['daysOfMonth'] ?? []) !== [])
                        <span>{{ __('ui.home.month_days', ['days' => implode(', ', $event['daysOfMonth'])]) }}</span>
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
            @endif
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
