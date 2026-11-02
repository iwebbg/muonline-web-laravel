@extends('layouts.app')

@section('title', __('ui.recovery.request_title'))

@section('content')
    <div class="shell grid min-h-[calc(100vh-12rem)] items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr] lg:py-20">
        <section class="max-w-2xl">
            <div class="eyebrow mb-5">{{ __('ui.recovery.eyebrow') }}</div>
            <h1 class="font-display text-5xl leading-[1.05] text-white sm:text-6xl">{{ __('ui.recovery.request_heading') }}</h1>
            <p class="mt-5 max-w-xl text-lg leading-8 text-silver">{{ __('ui.recovery.request_intro') }}</p>
        </section>

        <section class="panel-card p-6 sm:p-8">
            <p class="kicker">{{ __('ui.recovery.form_kicker') }}</p>
            <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.recovery.verify_identity') }}</h2>

            <form method="POST" action="{{ route('password.email') }}" class="mt-8 grid gap-5">
                @csrf

                @if (session('status'))
                    <div class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <label class="field-group">
                    <span>{{ __('ui.recovery.account') }}</span>
                    <input type="text" name="account" value="{{ old('account') }}" minlength="4" maxlength="10" autocomplete="username" required autofocus>
                </label>
                <label class="field-group">
                    <span>{{ __('ui.recovery.email') }}</span>
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="50" autocomplete="email" required>
                </label>

                @if ($errors->any())
                    <div class="rounded-xl border border-crimson/35 bg-crimson/10 px-4 py-3 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <button type="submit" class="button button-primary button-large mt-2 w-full">{{ __('ui.recovery.send_link') }}</button>
            </form>

            <p class="mt-5 text-center text-sm text-steel">
                <a href="{{ route('login') }}" class="font-semibold text-gold hover:text-white">{{ __('ui.recovery.back_to_login') }}</a>
            </p>
        </section>
    </div>
@endsection
