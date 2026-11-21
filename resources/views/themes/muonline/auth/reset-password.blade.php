@extends('layouts.app')

@section('title', __('ui.recovery.reset_title'))

@section('content')
    <div class="shell grid min-h-[calc(100vh-12rem)] items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr] lg:py-20">
        <section class="max-w-2xl">
            <div class="eyebrow mb-5">{{ __('ui.recovery.eyebrow') }}</div>
            <h1 class="font-display text-5xl leading-[1.05] text-white sm:text-6xl">{{ __('ui.recovery.reset_heading') }}</h1>
            <p class="mt-5 max-w-xl text-lg leading-8 text-silver">{{ __('ui.recovery.reset_intro') }}</p>
        </section>

        <section class="panel-card p-6 sm:p-8" x-data="{ passwordsVisible: false }">
            <p class="kicker">{{ __('ui.recovery.form_kicker') }}</p>
            <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.recovery.choose_password') }}</h2>

            <form method="POST" action="{{ route('password.update') }}" class="mt-8 grid gap-5">
                @csrf
                <input type="hidden" name="account" value="{{ $account }}">
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="rounded-xl border border-white/8 bg-black/20 px-4 py-3 text-sm text-silver">
                    {{ __('ui.recovery.account') }}: <strong class="text-white">{{ $account }}</strong>
                </div>
                <label class="field-group">
                    <span>{{ __('ui.recovery.new_password') }}</span>
                    <input x-bind:type="passwordsVisible ? 'text' : 'password'" name="password" minlength="4" maxlength="10" autocomplete="new-password" required autofocus>
                </label>
                <label class="field-group">
                    <span>{{ __('ui.recovery.confirm_password') }}</span>
                    <input x-bind:type="passwordsVisible ? 'text' : 'password'" name="password_confirmation" minlength="4" maxlength="10" autocomplete="new-password" required>
                </label>
                <label class="flex items-center gap-3 text-sm text-steel">
                    <input type="checkbox" x-model="passwordsVisible" class="size-4 accent-amber-400">
                    {{ __('ui.recovery.show_passwords') }}
                </label>

                @if ($errors->any())
                    <div class="rounded-xl border border-crimson/35 bg-crimson/10 px-4 py-3 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <button type="submit" class="button button-primary button-large mt-2 w-full">{{ __('ui.recovery.reset_submit') }}</button>
            </form>
        </section>
    </div>
@endsection
