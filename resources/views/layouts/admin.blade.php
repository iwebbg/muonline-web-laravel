@php
    $pageTitle = trim($__env->yieldContent('title', __('ui.navigation.administration')));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#08090c">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

        <title>{{ $pageTitle }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="min-h-screen bg-void text-mist antialiased"
        data-admin-layout
        x-data="{ adminMenuOpen: false }"
        x-on:keydown.escape.window="adminMenuOpen = false"
    >
        <div class="admin-background" aria-hidden="true"></div>

        @auth('admin')
            <div
                class="fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm xl:hidden"
                x-show="adminMenuOpen"
                x-transition.opacity
                x-on:click="adminMenuOpen = false"
                x-cloak
                aria-hidden="true"
            ></div>

            <x-admin-navigation
                :reset-revision="request()->routeIs('admin.character-rules.*') ? ($revision ?? null) : null"
                x-bind:style="adminMenuOpen ? 'transform: translateX(0)' : ''"
            />
        @endauth

        <div @class(['relative z-10 min-w-0', 'xl:pl-72' => auth('admin')->check()])>
            <header class="sticky top-0 z-50 border-b border-white/8 bg-void/90 backdrop-blur-xl">
                <div class="admin-shell flex min-h-16 flex-wrap items-center justify-between gap-3 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        @auth('admin')
                            <button
                                type="button"
                                class="icon-button xl:hidden"
                                aria-label="{{ __('ui.navigation.toggle') }}"
                                x-on:click="adminMenuOpen = true"
                            >
                                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 7h16M4 12h16M4 17h16" />
                                </svg>
                            </button>
                        @endauth

                        <a href="{{ auth('admin')->check() ? route('admin.dashboard') : route('admin.login') }}" class="min-w-0">
                            <span class="block truncate text-sm font-semibold tracking-[0.12em] text-white">{{ config('app.name') }}</span>
                            <span class="block text-[0.62rem] font-semibold uppercase tracking-[0.24em] text-gold">{{ __('ui.navigation.administration') }}</span>
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                        <a href="{{ route('home') }}" class="button button-ghost">
                            {{ __('ui.navigation.view_website') }}
                        </a>
                        <x-language-selector />

                        @auth('admin')
                            <span class="hidden max-w-48 truncate text-sm font-semibold text-silver md:inline">
                                {{ auth('admin')->user()->name }}
                            </span>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="button button-primary">{{ __('ui.navigation.logout') }}</button>
                            </form>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="min-h-[calc(100vh-4rem)]">
                @yield('content')
            </main>
        </div>
    </body>
</html>
