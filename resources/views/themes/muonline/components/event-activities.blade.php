@props(['activities'])

@if ($activities !== [])
    <section class="shell pb-14">
        <div class="section-heading">
            <div>
                <p class="kicker">{{ __('ui.home.activities_kicker') }}</p>
                <h2>{{ __('ui.home.activities_heading') }}</h2>
            </div>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($activities as $activity)
                <article class="panel-card p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="kicker">{{ $activity->location }}</p>
                        <span class="rounded-lg border border-white/10 px-2 py-1 text-xs text-silver">{{ __('ui.home.activity_availability.'.$activity->availability->value) }}</span>
                    </div>
                    <h3 class="mt-3 text-lg font-semibold text-white">{{ $activity->name }}</h3>
                    <p class="mt-3 text-sm leading-6 text-silver">{{ $activity->description }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif
