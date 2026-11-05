@extends('layouts.app')

@section('title', __('ui.admin.event_schedules_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.event_schedules_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.event_schedules_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="event-schedules" />

            <main class="grid min-w-0 content-start gap-6">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
                @endif

                <section class="panel-card p-5 sm:p-7">
                    <p class="kicker">{{ __('ui.admin.event_effective_schedule') }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-sm text-steel">
                        <span class="resource-chip">{{ $gameVersion }}</span>
                        <span class="resource-chip">{{ count($effectiveDefinitions) }} {{ __('ui.admin.event_definitions') }}</span>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($effectiveDefinitions as $definition)
                            <article class="rounded-2xl border border-white/8 bg-black/20 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <strong class="text-white">{{ $definition->name }}</strong>
                                        <p class="mt-1 text-sm text-steel">{{ $definition->location }}</p>
                                    </div>
                                    <span class="text-xs {{ $definition->enabled ? 'text-emerald-200' : 'text-steel' }}">{{ $definition->enabled ? __('ui.admin.event_active') : __('ui.admin.event_inactive') }}</span>
                                </div>
                                <p class="mt-3 text-xs text-silver">{{ $definition->intervalMinutes === null ? implode(', ', $definition->times) : __('ui.admin.event_every_minutes', ['count' => $definition->intervalMinutes]) }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="panel-card p-5 sm:p-7" x-data="{ scheduleMode: @js(old('schedule_mode', 'times')) }">
                    <p class="kicker">{{ __('ui.admin.event_create') }}</p>
                    <p class="mt-2 text-sm text-steel">{{ __('ui.admin.event_override_help') }}</p>
                    <form method="POST" action="{{ route('admin.event-schedules.store') }}" class="mt-5 grid gap-5">
                        @csrf
                        @include('admin.partials.event-schedule-fields', ['entry' => null])
                        <div class="flex justify-end"><button type="submit" class="button button-primary">{{ __('ui.admin.event_create_button') }}</button></div>
                    </form>
                </section>

                <section class="grid gap-4">
                    @forelse ($entries as $entry)
                        <article class="panel-card p-5 sm:p-7" x-data="{ editing: false, scheduleMode: @js($entry->schedule_mode) }">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="kicker">{{ $entry->event_key }} · {{ __('ui.admin.revision') }} {{ $entry->revision }}</p>
                                    <h2 class="mt-2 text-xl font-semibold text-white">{{ $entry->name_en }}</h2>
                                    <p class="mt-1 text-sm text-steel">{{ $entry->schedule_mode === 'interval' ? __('ui.admin.event_every_minutes', ['count' => $entry->interval_minutes]) : implode(', ', $entry->times) }}</p>
                                </div>
                                <button type="button" class="button button-ghost" @click="editing = ! editing">{{ __('ui.admin.event_edit') }}</button>
                            </div>
                            <form method="POST" action="{{ route('admin.event-schedules.update', $entry) }}" class="mt-6 grid gap-5 border-t border-white/8 pt-6" x-show="editing" x-cloak>
                                @csrf
                                @method('PUT')
                                @include('admin.partials.event-schedule-fields', ['entry' => $entry])
                                <div class="flex justify-end"><button type="submit" class="button button-primary">{{ __('ui.admin.event_save') }}</button></div>
                            </form>
                        </article>
                    @empty
                        <div class="panel-card p-8 text-center text-steel">{{ __('ui.admin.event_no_overrides') }}</div>
                    @endforelse
                </section>

                <section class="panel-card p-5 sm:p-7">
                    <p class="kicker">{{ __('ui.admin.event_revision_history') }}</p>
                    <div class="mt-4 grid gap-3">
                        @forelse ($recentRevisions as $revision)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/8 bg-black/20 p-4 text-sm">
                                <span class="text-white">#{{ $revision->event_schedule_entry_id }} · {{ $revision->action }} · r{{ $revision->revision }}</span>
                                <span class="text-steel">{{ $revision->created_at?->format('Y-m-d H:i:s') }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-steel">{{ __('ui.admin.event_no_revisions') }}</p>
                        @endforelse
                    </div>
                </section>
            </main>
        </div>
    </div>
@endsection
