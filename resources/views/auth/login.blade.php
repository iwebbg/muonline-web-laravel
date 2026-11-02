@extends('layouts.app')

@section('title', __('ui.auth.title'))

@section('content')
    <div class="shell grid min-h-[calc(100vh-12rem)] items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr] lg:py-20">
        <section class="max-w-2xl">
            <div class="eyebrow mb-5">{{ __('ui.auth.eyebrow') }}</div>
            <h1 class="font-display text-5xl leading-[1.05] text-white sm:text-6xl">
                {{ __('ui.auth.heading') }}
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-8 text-silver">
                {{ __('ui.auth.intro') }}
            </p>
            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                <div class="outcome-card"><span>{{ __('ui.auth.secure_session') }}</span><strong>{{ __('ui.auth.rotated') }}</strong></div>
                <div class="outcome-card"><span>{{ __('ui.auth.account_source') }}</span><strong>MEMB_INFO</strong></div>
                <div class="outcome-card"><span>{{ __('ui.auth.private_data') }}</span><strong>{{ __('ui.auth.ownership_scoped') }}</strong></div>
            </div>
        </section>

        <section class="panel-card p-6 sm:p-8" x-data="{ passwordVisible: false }">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="kicker">{{ __('ui.auth.form_kicker') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.auth.form_heading') }}</h2>
                </div>
                <span class="status-pill"><span class="status-dot"></span>{{ __('ui.auth.realm_online') }}</span>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 grid gap-5">
                @csrf

                @if (session('status'))
                    <div class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <label class="field-group">
                    <span>{{ __('ui.auth.username') }}</span>
                    <input
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        maxlength="10"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </label>

                <label class="field-group">
                    <span>{{ __('ui.auth.password') }}</span>
                    <div class="relative">
                        <input
                            :type="passwordVisible ? 'text' : 'password'"
                            name="password"
                            maxlength="10"
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

                @error('username')
                    <div class="rounded-xl border border-crimson/35 bg-crimson/10 px-4 py-3 text-sm text-red-100" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                <button type="submit" class="button button-primary button-large mt-2 w-full">
                    {{ __('ui.auth.submit') }}
                    <span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="mt-5 text-center text-xs leading-5 text-steel">{{ __('ui.auth.no_remember') }}</p>
            <p class="mt-3 text-center text-sm text-steel">
                {{ __('ui.registration.have_no_account') }}
                <a href="{{ route('register') }}" class="font-semibold text-gold hover:text-white">{{ __('ui.registration.open_form') }}</a>
            </p>
        </section>
    </div>
@endsection
