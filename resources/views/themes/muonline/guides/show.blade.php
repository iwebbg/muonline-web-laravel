@extends('layouts.app')

@section('title', $post->localizedTitle(app()->getLocale()).' · MuOnline Web')

@section('content')
    <article class="shell max-w-5xl py-12 sm:py-16 lg:py-20">
        <a href="{{ route('guides.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-gold hover:text-amber-200">
            <span aria-hidden="true">←</span> {{ __('ui.guides.back') }}
        </a>
        <header class="mt-8 border-b border-white/8 pb-8">
            <time class="kicker" datetime="{{ $post->published_at?->toAtomString() }}">{{ $post->published_at?->translatedFormat('d M Y · H:i') }}</time>
            <h1 class="mt-4 font-display text-4xl leading-tight text-white sm:text-6xl">{{ $post->localizedTitle(app()->getLocale()) }}</h1>
            <p class="mt-5 max-w-3xl text-lg leading-8 text-silver">{{ $post->localizedExcerpt(app()->getLocale()) }}</p>
        </header>
        <div class="panel-card mt-8 p-6 text-base leading-8 text-silver sm:p-9">
            {!! nl2br(e($post->localizedBody(app()->getLocale()))) !!}
        </div>
    </article>
@endsection
