@extends('layouts.app')

@section('title', __('ui.guides.title'))

@section('content')
    <section class="relative overflow-hidden border-b border-white/8">
        <div class="absolute inset-0 bg-[url('/images/backgrounds/default.jpg')] bg-cover bg-center opacity-20"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-ink/35 via-ink/80 to-ink"></div>
        <div class="shell relative py-14 sm:py-20">
            <p class="eyebrow">{{ __('ui.guides.eyebrow') }}</p>
            <h1 class="mt-4 max-w-4xl font-display text-5xl text-white sm:text-6xl">{{ __('ui.guides.heading') }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-silver">{{ __('ui.guides.intro') }}</p>
        </div>
    </section>

    <section class="shell py-10 lg:py-14">
        <div class="grid gap-5 lg:grid-cols-2">
            @forelse ($posts as $post)
                <article class="panel-card flex flex-col p-6 sm:p-7">
                    <time class="kicker" datetime="{{ $post->published_at?->toAtomString() }}">{{ $post->published_at?->translatedFormat('d M Y') }}</time>
                    <h2 class="mt-4 font-display text-3xl text-white">{{ $post->localizedTitle(app()->getLocale()) }}</h2>
                    <p class="mt-4 flex-1 leading-7 text-silver">{{ $post->localizedExcerpt(app()->getLocale()) }}</p>
                    <a href="{{ route('guides.show', $post->slug) }}" class="mt-6 inline-flex items-center gap-2 font-semibold text-gold hover:text-amber-200">
                        {{ __('ui.guides.read_more') }} <span aria-hidden="true">→</span>
                    </a>
                </article>
            @empty
                <article class="panel-card col-span-full py-16 text-center">
                    <p class="kicker">{{ __('ui.guides.empty') }}</p>
                    <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.guides.empty_help') }}</p>
                </article>
            @endforelse
        </div>

        @if ($posts->hasPages())
            <div class="mt-8">{{ $posts->links() }}</div>
        @endif
    </section>
@endsection
