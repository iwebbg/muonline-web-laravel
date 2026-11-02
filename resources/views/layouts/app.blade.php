<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#08090c">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <title>@yield('title', config('app.name'))</title>

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
                    <span class="brand-mark" aria-hidden="true">MU</span>
                    <span>
                        <span class="block font-display text-sm tracking-[0.22em] text-white">MUONLINE</span>
                        <span class="block text-[0.62rem] font-semibold uppercase tracking-[0.28em] text-gold">97d + 99i</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('ui.navigation.primary') }}">
                    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.realm') }}</a>
                    <a href="{{ route('home') }}#rankings" class="nav-link">{{ __('ui.navigation.rankings') }}</a>
                    <a href="{{ route('home') }}#events" class="nav-link">{{ __('ui.navigation.events') }}</a>
                    <a href="{{ route('account.dashboard') }}" class="nav-link {{ request()->routeIs('account.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.account') }}</a>
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.*') ? 'nav-link-active' : '' }}">{{ __('ui.navigation.admin') }}</a>
                </nav>

                <div class="hidden items-center gap-3 sm:flex">
                    <div class="status-pill">
                        <span class="status-dot"></span>
                        <span>{{ __('ui.navigation.server_online') }}</span>
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
                        <a href="{{ route('register') }}" class="button button-ghost">{{ __('ui.navigation.register') }}</a>
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
                    <a href="{{ route('home') }}#rankings" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.rankings') }}</a>
                    <a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.account_panel') }}</a>
                    @guest
                        <a href="{{ route('register') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.register') }}</a>
                    @endguest
                    <a href="{{ route('admin.dashboard') }}" class="mobile-nav-link" @click="closeMobileMenu">{{ __('ui.navigation.administration') }}</a>
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
            <div class="shell flex flex-col gap-4 py-8 text-sm text-steel sm:flex-row sm:items-center sm:justify-between">
                <p>{{ __('ui.footer.tagline') }}</p>
                <p>{{ __('ui.footer.prototype') }}</p>
            </div>
        </footer>
    </body>
</html>
