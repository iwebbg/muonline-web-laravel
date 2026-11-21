@extends('layouts.app')

@section('title', $page->localizedTitle(app()->getLocale()).' · '.config('app.name'))

@section('content')
    <article class="shell max-w-5xl py-12 sm:py-16 lg:py-20">
        <header class="border-b border-white/8 pb-8">
            <p class="kicker">{{ __('ui.pages.eyebrow') }}</p>
            <h1 class="mt-4 font-display text-4xl leading-tight text-white sm:text-6xl">
                {{ $page->localizedTitle(app()->getLocale()) }}
            </h1>
            <p class="mt-5 max-w-3xl text-lg leading-8 text-silver">
                {{ $page->localizedExcerpt(app()->getLocale()) }}
            </p>
        </header>

        <div class="panel-card mt-8 p-6 text-base leading-8 text-silver sm:p-9">
            {!! nl2br(e($page->localizedBody(app()->getLocale()))) !!}
        </div>
    </article>
@endsection
