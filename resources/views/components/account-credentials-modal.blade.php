@props([
    'account',
    'gameWritesEnabled',
])

<div
    {{ $attributes->class('contents') }}
    x-data="{
        credentialsOpen: @js(old('email') !== null),
        credentialsVisible: false,
    }"
    x-effect="document.body.style.overflow = credentialsOpen ? 'hidden' : ''"
>
    <button
        type="button"
        class="button button-ghost"
        x-ref="credentialsTrigger"
        @click="credentialsOpen = true; $nextTick(() => $refs.currentPassword.focus())"
    >
        <svg class="size-4 text-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M12 3 5.5 5.7v5.5c0 4.1 2.7 7.8 6.5 9.8 3.8-2 6.5-5.7 6.5-9.8V5.7L12 3Z" />
            <path d="M9.5 12 11 13.5l3.5-3.5" />
        </svg>
        {{ __('ui.account.credentials_manage') }}
    </button>

    <template x-teleport="body">
        <div
            class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-6"
            x-show="credentialsOpen"
            x-transition.opacity
            x-cloak
            @click.self="credentialsOpen = false; $nextTick(() => $refs.credentialsTrigger.focus())"
            @keydown.escape.window="
                if (credentialsOpen) {
                    credentialsOpen = false;
                    $nextTick(() => $refs.credentialsTrigger.focus());
                }
            "
        >
            <section
                class="panel-card my-auto w-full max-w-3xl shadow-2xl shadow-black/60"
                role="dialog"
                aria-modal="true"
                aria-labelledby="account-credentials-title"
            >
                <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                    <div>
                        <p class="kicker">{{ __('ui.account.credentials_kicker') }}</p>
                        <h2 id="account-credentials-title" class="mt-2 text-2xl font-semibold text-white">
                            {{ __('ui.account.credentials_heading') }}
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-silver">
                            {{ __('ui.account.credentials_help') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="icon-button shrink-0"
                        aria-label="{{ __('ui.account.credentials_close') }}"
                        @click="credentialsOpen = false; $nextTick(() => $refs.credentialsTrigger.focus())"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('account.credentials.update') }}" class="mt-6 grid gap-5 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <label class="field-group">
                        <span>{{ __('ui.account.credentials_current_password') }}</span>
                        <input
                            x-ref="currentPassword"
                            :type="credentialsVisible ? 'text' : 'password'"
                            name="currentPassword"
                            maxlength="10"
                            autocomplete="current-password"
                            required
                        >
                    </label>

                    <label class="field-group">
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

                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 text-sm text-steel">
                            <input type="checkbox" class="size-4" @change="credentialsVisible = $event.target.checked">
                            <span>{{ __('ui.account.credentials_show_passwords') }}</span>
                        </label>
                    </div>

                    @if ($errors->has('currentPassword') || $errors->has('email') || $errors->has('password'))
                        <div class="rounded-xl border border-crimson/35 bg-crimson/10 px-4 py-3 text-sm text-red-100 md:col-span-2" role="alert">
                            <ul class="grid gap-1">
                                @foreach ($errors->get('currentPassword') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                                @foreach ($errors->get('email') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                                @foreach ($errors->get('password') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="flex flex-col gap-4 border-t border-white/8 pt-5 md:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="max-w-xl text-sm leading-6 text-steel">
                            {{ $gameWritesEnabled ? __('ui.account.credentials_ready') : __('ui.account.credentials_writes_disabled') }}
                        </p>
                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                class="button button-ghost"
                                @click="credentialsOpen = false; $nextTick(() => $refs.credentialsTrigger.focus())"
                            >
                                {{ __('ui.account.credentials_cancel') }}
                            </button>
                            <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled)>
                                {{ __('ui.account.credentials_submit') }}
                            </button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </template>
</div>
