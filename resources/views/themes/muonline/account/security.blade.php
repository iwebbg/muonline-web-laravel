@extends('layouts.app')

@section('title', __('ui.account.security_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <x-account-feature-header
            :kicker="__('ui.account.credentials_kicker')"
            :heading="__('ui.account.security_heading')"
            :intro="__('ui.account.security_intro')"
            :show-back="false"
        />

        <x-account-navigation class="mt-6" />

        @if (session('accountStatus'))
            <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                {{ session('accountStatus') }}
            </div>
        @endif

        @if (! $gameWritesEnabled)
            <div class="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">
                {{ __('ui.account.credentials_writes_disabled') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('account.credentials.update') }}"
            class="panel-card mt-8 grid gap-8 p-6 lg:grid-cols-2"
            x-data="{ credentialsVisible: false }"
        >
            @csrf
            @method('PUT')

            <section>
                <p class="kicker">{{ __('ui.account.security_email_kicker') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('ui.account.security_email_heading') }}</h2>
                <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.security_email_help') }}</p>

                <label class="field-group mt-6">
                    <span>{{ __('ui.account.credentials_email') }}</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $account->email) }}"
                        maxlength="50"
                        autocomplete="email"
                        required
                    >
                </label>
            </section>

            <section>
                <p class="kicker">{{ __('ui.account.security_password_kicker') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('ui.account.security_password_heading') }}</h2>
                <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.security_password_help') }}</p>

                <div class="mt-6 grid gap-5">
                    <label class="field-group">
                        <span>{{ __('ui.account.credentials_new_password') }}</span>
                        <input
                            :type="credentialsVisible ? 'text' : 'password'"
                            name="password"
                            minlength="4"
                            maxlength="10"
                            pattern="[A-Za-z0-9_-]+"
                            autocomplete="new-password"
                        >
                        <small>{{ __('ui.account.credentials_password_optional') }}</small>
                    </label>

                    <label class="field-group">
                        <span>{{ __('ui.account.credentials_repeat_password') }}</span>
                        <input
                            :type="credentialsVisible ? 'text' : 'password'"
                            name="password_confirmation"
                            minlength="4"
                            maxlength="10"
                            pattern="[A-Za-z0-9_-]+"
                            autocomplete="new-password"
                        >
                    </label>

                    <label class="flex items-center gap-3 text-sm text-steel">
                        <input type="checkbox" class="size-4" @change="credentialsVisible = $event.target.checked">
                        <span>{{ __('ui.account.credentials_show_passwords') }}</span>
                    </label>
                </div>
            </section>

            <section class="border-t border-white/8 pt-6 lg:col-span-2">
                <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <label class="field-group">
                        <span>{{ __('ui.account.credentials_current_password') }}</span>
                        <input
                            :type="credentialsVisible ? 'text' : 'password'"
                            name="currentPassword"
                            maxlength="10"
                            autocomplete="current-password"
                            required
                        >
                        <small>{{ __('ui.account.security_confirmation_help') }}</small>
                    </label>
                    <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled)>
                        {{ __('ui.account.credentials_submit') }}
                    </button>
                </div>
            </section>

            @if ($errors->has('currentPassword') || $errors->has('email') || $errors->has('password'))
                <div class="rounded-xl border border-crimson/35 bg-crimson/10 px-4 py-3 text-sm text-red-100 lg:col-span-2" role="alert">
                    <ul class="grid gap-1">
                        @foreach (['currentPassword', 'email', 'password'] as $field)
                            @foreach ($errors->get($field) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif
        </form>

        <section class="panel-card mt-8 border-red-300/15 p-6">
            <p class="kicker text-red-200">{{ __('ui.account.privacy_kicker') }}</p>
            <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('ui.account.deletion_heading') }}</h2>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-silver">{{ __('ui.account.deletion_intro') }}</p>

            @if (session('deletionStatus'))
                <div class="mt-5 rounded-xl border border-emerald-300/25 bg-emerald-300/8 px-4 py-3 text-sm text-emerald-100" role="status">
                    {{ session('deletionStatus') }}
                </div>
            @endif

            @if ($deletionRequest?->status === 'verified')
                <div class="mt-6 rounded-xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm leading-6 text-amber-100">
                    {{ __('ui.account.deletion_verified_pending') }}
                </div>
            @else
                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('account.deletion-requests.store') }}" class="rounded-2xl border border-white/10 bg-black/15 p-5">
                        @csrf
                        <h3 class="text-lg font-semibold text-white">{{ __('ui.account.deletion_request_heading') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.deletion_request_help') }}</p>
                        <label class="field-group mt-5">
                            <span>{{ __('ui.account.credentials_current_password') }}</span>
                            <input type="password" name="currentPassword" maxlength="10" autocomplete="current-password" required>
                        </label>
                        @error('currentPassword')
                            <p class="mt-3 text-sm text-red-200" role="alert">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="button mt-5 border border-red-300/25 text-red-100 hover:bg-red-300/10">
                            {{ __('ui.account.deletion_send_code') }}
                        </button>
                    </form>

                    @php
                        $deletionRequestId = session('deletionRequestId')
                            ?? ($deletionRequest?->status === 'pending_verification' ? $deletionRequest->getKey() : null);
                    @endphp

                    <form method="POST" action="{{ route('account.deletion-requests.confirm') }}" class="rounded-2xl border border-white/10 bg-black/15 p-5">
                        @csrf
                        <input type="hidden" name="deletionRequest" value="{{ $deletionRequestId }}">
                        <h3 class="text-lg font-semibold text-white">{{ __('ui.account.deletion_confirm_heading') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.deletion_confirm_help') }}</p>
                        <label class="field-group mt-5">
                            <span>{{ __('ui.account.deletion_code') }}</span>
                            <input type="text" name="verificationCode" inputmode="numeric" minlength="6" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" required @disabled($deletionRequestId === null)>
                        </label>
                        @error('verificationCode')
                            <p class="mt-3 text-sm text-red-200" role="alert">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="button mt-5 border border-red-300/25 text-red-100 hover:bg-red-300/10" @disabled($deletionRequestId === null)>
                            {{ __('ui.account.deletion_verify') }}
                        </button>
                    </form>
                </div>
            @endif
        </section>
    </div>
@endsection
