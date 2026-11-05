@php
    $pageTitle = $websiteSettings->documentTitle(
        trim($__env->yieldContent('title', config('app.name'))),
        false,
        app()->getLocale(),
    );
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#08090c">
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <title>{{ $pageTitle }}</title>

        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-void text-mist antialiased">
        <div class="site-background" aria-hidden="true"></div>
        <div class="site-vignette" aria-hidden="true"></div>

        <div class="relative z-10 flex min-h-screen flex-col">
            <header class="border-b border-white/8 bg-void/82 backdrop-blur-xl">
                <div class="shell flex h-18 items-center justify-between gap-6">
                    <a href="{{ url('/') }}" class="group flex items-center gap-3" aria-label="{{ __('ui.navigation.brand_home') }}">
                        <span class="brand-mark overflow-hidden" aria-hidden="true">
                            @if ($websiteLogoUrl !== null)
                                <img src="{{ $websiteLogoUrl }}" alt="" class="size-full object-cover">
                            @else
                                MU
                            @endif
                        </span>
                        <span>
                            <span class="block font-display text-sm tracking-[0.22em] text-white">{{ $websiteSettings->brandTitle }}</span>
                            <span class="block text-[0.62rem] font-semibold uppercase tracking-[0.28em] text-gold">{{ $websiteSettings->brandSubtitle }}</span>
                        </span>
                    </a>
                    <span class="status-pill">{{ __('ui.errors.system_status') }}</span>
                </div>
            </header>

            <main class="shell grid flex-1 place-items-center py-16 sm:py-24">
                <section class="panel-card relative w-full max-w-3xl overflow-hidden p-7 sm:p-10 lg:p-12" aria-labelledby="error-title">
                    <div class="pointer-events-none absolute -right-20 -top-24 size-64 rounded-full bg-crimson/10 blur-3xl" aria-hidden="true"></div>
                    <div class="relative grid gap-8 md:grid-cols-[10rem_minmax(0,1fr)] md:items-center md:gap-10">
                        <div class="flex items-center gap-4 md:block">
                            <p class="font-display text-6xl leading-none text-gold sm:text-7xl md:text-8xl" aria-hidden="true">
                                @yield('code')
                            </p>
                            <div class="h-px flex-1 bg-gradient-to-r from-gold/50 to-transparent md:mt-5 md:w-full"></div>
                        </div>

                        <div>
                            <p class="kicker">{{ __('ui.errors.eyebrow') }}</p>
                            <h1 id="error-title" class="mt-3 font-display text-3xl text-white sm:text-4xl">
                                @yield('message')
                            </h1>
                            <p class="mt-4 max-w-xl text-base leading-7 text-silver sm:text-lg">
                                @yield('description')
                            </p>
                            <div class="mt-7 flex flex-wrap gap-3">
                                <a href="{{ url('/') }}" class="button button-primary">{{ __('ui.errors.home_action') }}</a>
                                @hasSection('secondary_action_url')
                                    <a href="@yield('secondary_action_url')" class="button button-ghost">@yield('secondary_action_label')</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-white/8 bg-void/75">
                <div class="shell py-6 text-sm text-steel">
                    &copy; {{ now()->year }} {{ $websiteSettings->resolvedCopyrightHolder() }}.
                    {{ __('ui.footer.created_by') }}
                    <a href="https://interweb.bg" class="font-semibold text-silver transition hover:text-gold" target="_blank" rel="noopener noreferrer">InterWeb Ltd.</a>
                </div>
            </footer>
        </div>
    </body>
</html>
