@extends('layouts.app')

@section('title', __('ui.registration.title'))

@section('content')
    <div class="shell grid min-h-[calc(100vh-12rem)] items-center gap-10 py-12 lg:grid-cols-[1.05fr_0.95fr] lg:py-20">
        <section class="max-w-2xl">
            <div class="eyebrow mb-5">{{ __('ui.registration.eyebrow') }}</div>
            <h1 class="font-display text-5xl leading-[1.05] text-white sm:text-6xl">
                {{ __('ui.registration.heading') }}
            </h1>
            <p class="mt-5 max-w-xl text-lg leading-8 text-silver">
                {{ __('ui.registration.intro') }}
            </p>
            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                <div class="outcome-card"><span>{{ __('ui.registration.accounts') }}</span><strong>{{ number_format($realmStatistics['accounts']) }}</strong></div>
                <div class="outcome-card"><span>{{ __('ui.registration.characters') }}</span><strong>{{ number_format($realmStatistics['characters']) }}</strong></div>
                <div class="outcome-card"><span>{{ __('ui.registration.guilds') }}</span><strong>{{ number_format($realmStatistics['guilds']) }}</strong></div>
            </div>
        </section>

        <section class="panel-card p-6 sm:p-8" x-data="{ passwordVisible: false }">
            <div>
                <p class="kicker">{{ __('ui.registration.form_kicker') }}</p>
                <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.registration.form_heading') }}</h2>
            </div>

            <form
                method="POST"
                action="{{ route('register.store') }}"
                class="mt-8 grid gap-5"
                x-data="registrationRecaptcha(
                    @js($recaptchaSiteKey),
                    @js($recaptchaEnabled),
                    @js(__('ui.registration.recaptcha_failed'))
                )"
                @submit.prevent="submit($el)"
            >
                @csrf
                <input type="hidden" name="recaptcha_token" x-ref="recaptchaToken">

                <label class="field-group">
                    <span>{{ __('ui.registration.username') }}</span>
                    <input
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        minlength="4"
                        maxlength="10"
                        pattern="[A-Za-z0-9_-]+"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </label>

                <label class="field-group">
                    <span>{{ __('ui.registration.email') }}</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        maxlength="50"
                        autocomplete="email"
                        required
                    >
                </label>

                <label class="field-group">
                    <span>{{ __('ui.registration.password') }}</span>
                    <input
                        :type="passwordVisible ? 'text' : 'password'"
                        name="password"
                        minlength="4"
                        maxlength="10"
                        pattern="[A-Za-z0-9_-]+"
                        autocomplete="new-password"
                        required
                    >
                </label>

                <label class="field-group">
                    <span>{{ __('ui.registration.password_confirmation_label') }}</span>
                    <input
                        :type="passwordVisible ? 'text' : 'password'"
                        name="password_confirmation"
                        minlength="4"
                        maxlength="10"
                        pattern="[A-Za-z0-9_-]+"
                        autocomplete="new-password"
                        required
                    >
                </label>

                <label class="flex items-center gap-3 text-sm text-steel">
                    <input type="checkbox" class="size-4" @change="passwordVisible = $event.target.checked">
                    <span>{{ __('ui.registration.show_passwords') }}</span>
                </label>

                @if ($errors->any())
                    <div class="rounded-xl border border-crimson/35 bg-crimson/10 px-4 py-3 text-sm text-red-100" role="alert">
                        <ul class="grid gap-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p x-show="failureMessage" x-cloak class="text-sm text-red-200" x-text="failureMessage"></p>

                <button type="submit" class="button button-primary button-large mt-2 w-full" :disabled="submitting">
                    {{ __('ui.registration.submit') }}
                    <span aria-hidden="true">→</span>
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-steel">
                {{ __('ui.registration.have_account') }}
                <a href="{{ route('login') }}" class="font-semibold text-gold hover:text-white">{{ __('ui.registration.sign_in') }}</a>
            </p>
        </section>
    </div>

    @if ($recaptchaEnabled && $recaptchaSiteKey !== '')
        <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptchaSiteKey) }}" async defer></script>
    @endif
@endsection
