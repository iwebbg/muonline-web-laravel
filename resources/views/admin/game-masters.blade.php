@extends('layouts.admin')

@section('title', __('ui.admin.game_masters_title'))

@section('content')
    <div
        class="shell py-10 lg:py-14"
        x-data="gameMasterManager(@js(route('admin.game-masters.store')))"
        x-on:keydown.escape.window="closeEditor()"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.game_masters_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.game_masters_intro') }}</p>
            </div>
            <button type="button" class="button button-primary" x-on:click="openCreate()">
                {{ __('ui.admin.game_master_create') }}
            </button>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="game-masters" />

            <main class="grid min-w-0 content-start gap-4">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                @forelse ($gameMasters as $gameMaster)
                    <article class="panel-card flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span @class([
                                    'rounded-full border px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.12em]',
                                    'border-emerald-300/25 text-emerald-200' => $gameMaster['is_active'],
                                    'border-red-300/25 text-red-200' => ! $gameMaster['is_active'],
                                ])>
                                    {{ $gameMaster['is_active'] ? __('ui.admin.game_master_active') : __('ui.admin.game_master_inactive') }}
                                </span>
                                <span class="text-xs text-steel">
                                    {{ __('ui.admin.game_master_permission_count', ['count' => count($gameMaster['permissions'])]) }}
                                </span>
                            </div>
                            <h2 class="mt-3 truncate text-xl font-semibold text-white">{{ $gameMaster['name'] }}</h2>
                            <p class="mt-1 truncate text-sm text-steel">{{ $gameMaster['email'] }}</p>
                            <p class="mt-2 text-xs text-steel">
                                {{ $gameMaster['last_login_at'] !== '' ? __('ui.admin.game_master_last_login', ['time' => $gameMaster['last_login_at']]) : __('ui.admin.game_master_never_logged_in') }}
                            </p>
                        </div>
                        <button type="button" class="button button-ghost" x-on:click="openEdit(@js($gameMaster))">
                            {{ __('ui.admin.game_master_edit') }}
                        </button>
                    </article>
                @empty
                    <article class="panel-card py-14 text-center">
                        <p class="kicker">{{ __('ui.admin.game_master_empty') }}</p>
                        <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.admin.game_master_empty_help') }}</p>
                    </article>
                @endforelse
            </main>
        </div>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="editorOpen"
                class="fixed inset-0 z-[120] overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-8"
                x-on:click.self="closeEditor()"
                role="dialog"
                aria-modal="true"
                aria-labelledby="game-master-editor-title"
            >
                <section class="panel-card mx-auto w-full max-w-3xl p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="kicker">{{ __('ui.admin.game_masters') }}</p>
                        <h2 id="game-master-editor-title" class="mt-2 text-2xl font-semibold text-white" x-text="editing ? @js(__('ui.admin.game_master_edit_heading')) : @js(__('ui.admin.game_master_create_heading'))"></h2>
                    </div>
                    <button type="button" class="icon-button" x-on:click="closeEditor()" aria-label="{{ __('ui.admin.game_master_close') }}">×</button>
                </div>

                <form method="POST" x-bind:action="formAction" class="mt-6 grid gap-5">
                    @csrf
                    <input x-show="editing" type="hidden" name="_method" value="PUT" x-bind:disabled="!editing">

                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="field-group">
                            <span>{{ __('ui.admin.game_master_name') }}</span>
                            <input x-ref="gameMasterName" type="text" name="name" x-model="form.name" required maxlength="255">
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.game_master_email') }}</span>
                            <input type="email" name="email" x-model="form.email" required maxlength="255">
                        </label>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="field-group">
                            <span x-text="editing ? @js(__('ui.admin.game_master_new_password')) : @js(__('ui.admin.game_master_password'))"></span>
                            <input type="password" name="password" x-model="form.password" x-bind:required="!editing" autocomplete="new-password">
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.game_master_confirm_password') }}</span>
                            <input type="password" name="password_confirmation" x-model="form.password_confirmation" x-bind:required="!editing" autocomplete="new-password">
                        </label>
                    </div>
                    <p class="-mt-2 text-xs text-steel">{{ __('ui.admin.game_master_password_help') }}</p>

                    <fieldset>
                        <legend class="text-sm font-semibold text-white">{{ __('ui.admin.game_master_permissions') }}</legend>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                <label class="flex items-center gap-3 rounded-xl border border-white/8 bg-black/20 p-3 text-sm text-silver">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission['value'] }}"
                                        x-model="form.permissions"
                                        class="size-4 accent-amber-400"
                                    >
                                    {{ $permission['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <label x-show="editing" class="flex items-center gap-3 rounded-xl border border-white/8 bg-black/20 p-4 text-sm text-silver">
                        <input type="hidden" name="is_active" value="0" x-bind:disabled="!editing">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" x-bind:disabled="!editing" class="size-4 accent-amber-400">
                        {{ __('ui.admin.game_master_can_login') }}
                    </label>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" class="button button-ghost" x-on:click="closeEditor()">{{ __('ui.admin.game_master_cancel') }}</button>
                        <button type="submit" class="button button-primary">{{ __('ui.admin.game_master_save') }}</button>
                    </div>
                </form>
                </section>
            </div>
        </template>
    </div>
@endsection
