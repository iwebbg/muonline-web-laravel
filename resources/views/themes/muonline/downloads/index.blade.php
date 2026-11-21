@extends('layouts.app')

@section('title', __('ui.downloads.title'))

@section('content')
    <section class="relative overflow-hidden border-b border-white/8">
        <div class="absolute inset-0 bg-[url('/images/backgrounds/default.jpg')] bg-cover bg-center opacity-20"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-ink/35 via-ink/80 to-ink"></div>
        <div class="shell relative py-14 sm:py-20">
            <p class="eyebrow">{{ __('ui.downloads.eyebrow') }}</p>
            <h1 class="mt-4 max-w-4xl font-display text-5xl text-white sm:text-6xl">{{ __('ui.downloads.heading') }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-silver">{{ __('ui.downloads.intro') }}</p>
        </div>
    </section>

    <section class="shell py-10 lg:py-14">
        <div class="grid gap-5 lg:grid-cols-2">
            @forelse ($downloads as $download)
                <article class="panel-card flex flex-col p-6 sm:p-7">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-gold/25 bg-gold/8 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.12em] text-gold">
                            {{ __('ui.downloads.categories.'.$download->category->value) }}
                        </span>
                        @if ($download->version)
                            <span class="text-xs text-steel">{{ __('ui.downloads.version', ['version' => $download->version]) }}</span>
                        @endif
                        @if ($download->file_size)
                            <span class="text-xs text-steel">· {{ $download->file_size }}</span>
                        @endif
                    </div>
                    <h2 class="mt-4 font-display text-3xl text-white">{{ $download->localizedTitle(app()->getLocale()) }}</h2>
                    <p class="mt-4 flex-1 whitespace-pre-line leading-7 text-silver">{{ $download->localizedDescription(app()->getLocale()) }}</p>
                    @if ($download->checksum_sha256)
                        <div class="mt-5 rounded-xl border border-white/8 bg-black/20 p-3">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-steel">{{ __('ui.downloads.sha256') }}</p>
                            <code class="mt-2 block break-all text-xs text-silver">{{ $download->checksum_sha256 }}</code>
                        </div>
                    @endif
                    <a
                        href="{{ $download->download_url }}"
                        class="button button-primary mt-6 w-full justify-center sm:w-fit"
                        target="_blank"
                        rel="noopener noreferrer"
                    >{{ __('ui.downloads.download') }} <span aria-hidden="true">↗</span></a>
                </article>
            @empty
                <article class="panel-card col-span-full py-16 text-center">
                    <p class="kicker">{{ __('ui.downloads.empty') }}</p>
                    <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.downloads.empty_help') }}</p>
                </article>
            @endforelse
        </div>
    </section>
@endsection
