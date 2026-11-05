@inject('moduleManager', 'App\Modules\ModuleManager')
@php
    $pageTitle = $websiteSettings->documentTitle(
        trim($__env->yieldContent('title', config('app.name'))),
        request()->routeIs('home'),
        app()->getLocale(),
    );
    $metaDescription = trim($__env->yieldContent(
        'meta_description',
        $websiteSettings->localizedDescription(app()->getLocale()) ?? '',
    ));
    $metaKeywords = trim($__env->yieldContent(
        'meta_keywords',
        $websiteSettings->localizedKeywords(app()->getLocale()) ?? '',
    ));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#08090c">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <title>{{ $pageTitle }}</title>
        @if ($metaDescription !== '')
            <meta name="description" content="{{ $metaDescription }}">
        @endif
        @if ($metaKeywords !== '')
            <meta name="keywords" content="{{ $metaKeywords }}">
        @endif
        <meta property="og:title" content="{{ $pageTitle }}">
        @if ($metaDescription !== '')
            <meta property="og:description" content="{{ $metaDescription }}">
        @endif
        <meta property="og:type" content="website">
        @if ($websiteOgImageUrl !== null)
            <meta property="og:image" content="{{ $websiteOgImageUrl }}">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="min-h-screen bg-void text-mist antialiased"
        x-data="applicationNavigation"
    >
        <div class="site-background" aria-hidden="true"></div>
        <div class="site-vignette" aria-hidden="true"></div>

        <header class="sticky top-0 z-50 border-b border-white/8 bg-void/82 backdrop-blur-xl">
            <div class="shell flex h-18 items-center justify-between gap-6">
                <a href="{{ route('home') }}" class="group flex items-center gap-3" aria-label="{{ __('ui.navigation.brand_home') }}">
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

                <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('ui.navigation.primary') }}">
                    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.realm') }}</a>
                    <a href="{{ route('rankings') }}" class="nav-link">{{ __('ui.navigation.rankings') }}</a>
                    <a href="{{ route('news.index') }}" class="nav-link {{ request()->routeIs('news.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.news') }}</a>
                    <a href="{{ route('guides.index') }}" class="nav-link {{ request()->routeIs('guides.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.guides') }}</a>
                    <a href="{{ route('downloads.index') }}" class="nav-link {{ request()->routeIs('downloads.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.downloads') }}</a>
                    @if ($moduleManager->enabled(\App\Modules\ServerModule::Market))
                        <a href="{{ route('market.index') }}" class="nav-link {{ request()->routeIs('market.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.market') }}</a>
                    @endif
                    @if ($moduleManager->enabled(\App\Modules\ServerModule::WebShop) || $moduleManager->enabled(\App\Modules\ServerModule::MysteryBoxes))
                        <a href="{{ $moduleManager->enabled(\App\Modules\ServerModule::WebShop) ? route('shop.items.index') : route('shop.mystery-boxes.index') }}" class="nav-link {{ request()->routeIs('shop.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.shop') }}</a>
                    @endif
                    <a href="{{ route('home') }}#events" class="nav-link">{{ __('ui.navigation.events') }}</a>
                    <a href="{{ route('account.dashboard') }}" class="nav-link {{ request()->routeIs('account.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.account') }}</a>
                </nav>

                <div class="hidden items-center gap-3 sm:flex">
                    <div class="status-pill">
                        <span @class(['status-dot', 'status-dot-server-offline' => ! $gameServerOnline])></span>
                        <span>{{ $gameServerOnline ? __('ui.navigation.server_online') : __('ui.navigation.server_offline') }}</span>
                    </div>
                    <div class="language-switcher" role="group" aria-label="{{ __('ui.language.label') }}">
                        @foreach (['en' => 'EN', 'bg' => 'BG'] as $locale => $label)
                            <form method="POST" action="{{ route('locale.update', $locale) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="language-option {{ app()->isLocale($locale) ? 'language-option-active' : '' }}"
                                    aria-pressed="{{ app()->isLocale($locale) ? 'true' : 'false' }}"
                                >{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                    @auth
                        <a href="{{ route('account.dashboard') }}" class="button button-ghost">{{ auth()->user()->username }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="button button-primary">{{ __('ui.navigation.logout') }}</button>
                        </form>
                    @else
                        @if ($moduleManager->enabled(\App\Modules\ServerModule::Registration))
                            <a href="{{ route('register') }}" class="button button-ghost">{{ __('ui.navigation.register') }}</a>
                        @endif
                        <a href="{{ route('login') }}" class="button button-primary">{{ __('ui.navigation.enter_account') }}</a>
                    @endauth
                </div>

                <button
                    type="button"
                    class="icon-button lg:hidden"
                    aria-label="{{ __('ui.navigation.toggle') }}"
                    :aria-expanded="mobileMenuOpen"
                    @click="toggleMobileMenu"
                >
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path x-show="!mobileMenuOpen" d="M4 7h16M4 12h16M4 17h16" />
                        <path x-show="mobileMenuOpen" x-cloak d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <nav
                class="shell border-t border-white/8 py-4 lg:hidden"
                aria-label="{{ __('ui.navigation.mobile') }}"
                x-show="mobileMenuOpen"
                x-transition.opacity
                x-cloak
            >
                <div class="grid gap-1">
                    <a href="{{ route('home') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.realm') }}</a>
                    <a href="{{ route('rankings') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.rankings') }}</a>
                    <a href="{{ route('news.index') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.news') }}</a>
                    <a href="{{ route('guides.index') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.guides') }}</a>
                    <a href="{{ route('downloads.index') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.downloads') }}</a>
                    @if ($moduleManager->enabled(\App\Modules\ServerModule::Market))
                        <a href="{{ route('market.index') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.market') }}</a>
                    @endif
                    @if ($moduleManager->enabled(\App\Modules\ServerModule::WebShop) || $moduleManager->enabled(\App\Modules\ServerModule::MysteryBoxes))
                        <a href="{{ $moduleManager->enabled(\App\Modules\ServerModule::WebShop) ? route('shop.items.index') : route('shop.mystery-boxes.index') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.shop') }}</a>
                    @endif
                    <a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.account_panel') }}</a>
                    @guest
                        @if ($moduleManager->enabled(\App\Modules\ServerModule::Registration))
                        <a href="{{ route('register') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.register') }}</a>
                        @endif
                    @endguest
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="mobile-nav-link w-full text-left">{{ __('ui.navigation.logout') }}</button>
                        </form>
                    @endauth
                    <div class="language-switcher mt-3 w-fit" role="group" aria-label="{{ __('ui.language.label') }}">
                        @foreach (['en' => 'EN', 'bg' => 'BG'] as $locale => $label)
                            <form method="POST" action="{{ route('locale.update', $locale) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="language-option {{ app()->isLocale($locale) ? 'language-option-active' : '' }}"
                                    aria-pressed="{{ app()->isLocale($locale) ? 'true' : 'false' }}"
                                >{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </nav>
        </header>

        <main class="relative z-10">
            @yield('content')
        </main>

        <footer class="relative z-10 mt-20 border-t border-white/8 bg-void/75">
            <div class="shell flex flex-col gap-5 py-8 text-sm text-steel lg:flex-row lg:items-center lg:justify-between">
                <p>
                    &copy; {{ now()->year }} {{ $websiteSettings->resolvedCopyrightHolder() }}.
                    {{ __('ui.footer.created_by') }}
                    <a href="https://interweb.bg" class="font-semibold text-silver transition hover:text-gold" target="_blank" rel="noopener noreferrer">InterWeb Ltd.</a>
                </p>
                <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="{{ __('ui.footer.legal_navigation') }}">
                    <a href="{{ route('pages.show', 'cookies') }}" class="transition hover:text-gold">{{ __('ui.footer.cookies') }}</a>
                    <a href="{{ route('pages.show', 'privacy-policy') }}" class="transition hover:text-gold">{{ __('ui.footer.privacy') }}</a>
                    <a href="{{ route('pages.show', 'terms-of-use') }}" class="transition hover:text-gold">{{ __('ui.footer.terms') }}</a>
                </nav>
            </div>
        </footer>

        <aside
            x-cloak
            x-show="cookieNoticeVisible"
            x-transition.opacity
            class="fixed inset-x-4 bottom-4 z-[80] mx-auto max-w-4xl rounded-2xl border border-gold/25 bg-panel/95 p-5 shadow-2xl shadow-black/50 backdrop-blur-xl sm:p-6"
            role="dialog"
            aria-live="polite"
            aria-labelledby="cookie-notice-title"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="cookie-notice-title" class="text-base font-semibold text-white">{{ __('ui.cookies.title') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-silver">
                        {{ __('ui.cookies.message') }}
                        <a href="{{ route('pages.show', 'cookies') }}" class="font-semibold text-gold hover:text-amber-200">{{ __('ui.cookies.learn_more') }}</a>
                    </p>
                </div>
                <button type="button" class="button button-primary shrink-0" x-on:click="acknowledgeCookieNotice">
                    {{ __('ui.cookies.acknowledge') }}
                </button>
            </div>
        </aside>
    </body>
</html>
