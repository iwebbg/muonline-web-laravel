@extends('layouts.app')

@section('title', __('ui.events.title'))

@section('content')
    <section class="shell pt-14 pb-8 lg:pt-20">
        <p class="kicker">{{ __('ui.home.events_kicker') }}</p>
        <h1 class="mt-3 font-display text-4xl text-white sm:text-5xl">{{ __('ui.events.heading') }}</h1>
        <p class="mt-4 max-w-3xl text-silver">{{ __('ui.events.intro') }}</p>
        <p class="mt-3 text-sm text-steel">{{ __('ui.home.schedule_timezone', ['timezone' => $eventTimezone]) }}</p>
    </section>

    <section class="shell pb-14" x-data="eventSchedule({{ Js::from($events) }}, {{ Js::from($eventServerNow) }})">
        <div class="section-heading">
            <div>
                <p class="kicker">{{ __('ui.home.daily_schedule') }}</p>
                <h2>{{ __('ui.home.full_schedule') }}</h2>
            </div>
        </div>
        @if ($events !== [])
            <x-event-cards :events="$events" :show-schedule="true" />
        @else
            <p class="panel-card mt-8 p-6 text-silver">{{ __('ui.events.empty') }}</p>
        @endif
    </section>

    <x-event-activities :activities="$eventActivities" />
@endsection
