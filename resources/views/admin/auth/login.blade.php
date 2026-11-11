@extends('layouts.admin')

@section('title', __('ui.admin_auth.title'))

@section('content')
    <div class="shell grid min-h-[calc(100vh-12rem)] items-center gap-10 py-12 lg:grid-cols-[1fr_0.9fr] lg:py-20">
        <section class="max-w-2xl">
            <div class="eyebrow mb-5">{{ __('ui.admin_auth.eyebrow') }}</div>
            <h1 class="font-display text-5xl leading-[1.05] text-white sm:text-6xl">
                {{ __('ui.admin_auth.heading') }}
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-8 text-silver">
                {{ __('ui.admin_auth.intro') }}
            </p>
            <div class="mt-8 rounded-2xl border border-amber-300/20 bg-amber-300/7 p-5 text-sm leading-6 text-amber-100">
                <strong class="block text-amber-200">{{ __('ui.admin_auth.separate_identity') }}</strong>
                {{ __('ui.admin_auth.separate_identity_help') }}
            </div>
        </section>

        <section class="panel-card p-6 sm:p-8" x-data="{ passwordVisible: false }">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="kicker">{{ __('ui.admin_auth.form_kicker') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.admin_auth.form_heading') }}</h2>
                </div>
                <span class="rounded-full border border-crimson/30 bg-crimson/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-red-100">
                    {{ __('ui.admin_auth.restricted') }}
                </span>
            </div>

            <form method="POST" action="{{ route('admin.login.store') }}" class="mt-8 grid gap-5">
                @csrf

                <label class="field-group">
                    <span>{{ __('ui.admin_auth.email') }}</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        maxlength="255"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </label>

                <label class="field-group">
                    <span>{{ __('ui.admin_auth.password') }}</span>
                    <div class="relative">
                        <input
                            :type="passwordVisible ? 'text' : 'password'"
                            name="password"
                            maxlength="255"
                            autocomplete="current-password"
                            class="w-full pr-20"
                            required
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-3 text-xs font-bold uppercase tracking-wider text-gold"
                            @click="passwordVisible = !passwordVisible"
                            x-text="passwordVisible ? @js(__('ui.auth.hide_password')) : @js(__('ui.auth.show_password'))"
                        ></button>
                    </div>
                </label>

                @error('email')
                    <div class="rounded-xl border border-crimson/35 bg-crimson/10 px-4 py-3 text-sm text-red-100" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                <button type="submit" class="button button-primary button-large mt-2 w-full">
                    {{ __('ui.admin_auth.submit') }}
                    <span aria-hidden="true">→</span>
                </button>
            </form>
        </section>
    </div>
@endsection
