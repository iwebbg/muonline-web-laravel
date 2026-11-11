@extends('layouts.admin')

@section('title', __('ui.admin.server_title'))

@section('content')
    <div
        class="shell py-10 lg:py-14"
        x-data="gameServerManager(@js($servers), @js(route('admin.game-servers.store')), @js($currentGameServerVersion))"
        x-on:keydown.escape.window="editorOpen ? closeEditor() : closeDelete()"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.server_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.server_intro') }}</p>
            </div>
            <button type="button" class="button button-primary" x-on:click="openCreate()">{{ __('ui.admin.server_create') }}</button>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="game-servers" />

            <main class="grid min-w-0 content-start gap-4">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
                @endif

                @forelse ($servers as $server)
                    <article @class([
                        'panel-card p-5 sm:p-6',
                        'border-gold/30 bg-gold/[0.025]' => $server['is_current'],
                    ])>
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($server['is_current'])
                                        <span class="rounded-full border border-gold/30 bg-gold/10 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-widest text-gold">{{ __('ui.admin.server_current') }}</span>
                                    @else
                                        <span class="rounded-full border border-sky-300/25 bg-sky-300/8 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-widest text-sky-200">{{ __('ui.admin.server_external') }}</span>
                                    @endif
                                    <span class="rounded-full border border-white/10 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-widest text-steel">{{ $server['is_visible'] ? __('ui.admin.server_visible') : __('ui.admin.server_hidden') }}</span>
                                    <span class="rounded-full border border-violet-300/25 bg-violet-300/8 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-widest text-violet-200">{{ __('ui.admin.server_status_api') }}</span>
                                </div>
                                <h2 class="mt-3 truncate text-xl font-semibold text-white">{{ $server['name'] }}</h2>
                                <p class="mt-1 text-sm text-steel">{{ $server['version'] }} · {{ $server['experience'] }} · {{ $server['drop'] }}</p>
                                <p class="mt-2 truncate text-xs text-steel">{{ $server['is_current'] ? __('ui.admin.server_local_data_help') : $server['website_url'] }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-3">
                                <span class="text-xs text-steel">{{ $server['show_online_status'] ? __('ui.admin.server_status_visible') : __('ui.admin.server_status_hidden') }}</span>
                                <span class="text-xs text-steel">{{ $server['show_online_users'] ? __('ui.admin.server_users_visible') : __('ui.admin.server_users_hidden') }}</span>
                                <button type="button" class="button button-ghost" x-on:click="openEdit(@js($server))">{{ __('ui.admin.news_edit') }}</button>
                                @if (! $server['is_current'])
                                    <button type="button" class="button button-ghost text-red-200" x-on:click="openDelete(@js($server))">{{ __('ui.admin.news_delete') }}</button>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <article class="panel-card py-14 text-center">
                        <p class="kicker">{{ __('ui.admin.server_empty') }}</p>
                        <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.admin.server_empty_help') }}</p>
                    </article>
                @endforelse
            </main>
        </div>

        <template x-teleport="body">
            <div x-cloak x-show="editorOpen" class="fixed inset-0 z-[120] overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-8" x-on:click.self="closeEditor()" role="dialog" aria-modal="true" aria-labelledby="server-editor-title">
                <section class="panel-card mx-auto w-full max-w-4xl p-5 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="kicker">{{ __('ui.admin.game_servers_nav') }}</p>
                        <h2 id="server-editor-title" class="mt-2 text-2xl font-semibold text-white" x-text="editing ? @js(__('ui.admin.server_edit_heading')) : @js(__('ui.admin.server_create_heading'))"></h2>
                    </div>
                    <button type="button" class="icon-button" x-on:click="closeEditor()" aria-label="{{ __('ui.admin.news_close') }}">×</button>
                </div>

                <form method="POST" x-bind:action="formAction" class="mt-6 grid gap-5">
                    @csrf
                    <input x-show="editing" type="hidden" name="_method" value="PUT" x-bind:disabled="!editing">

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="field-group"><span>{{ __('ui.admin.server_name') }}</span><input x-ref="serverName" type="text" name="name" x-model="form.name" required maxlength="160"></label>
                        <label class="field-group"><span>{{ __('ui.admin.server_slug') }}</span><input type="text" name="slug" x-model="form.slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label>
                    </div>
                    <div class="grid gap-5 md:grid-cols-3">
                        <label class="field-group"><span>{{ __('ui.admin.server_version') }}</span><input type="text" name="version" x-model="form.version" required maxlength="80"></label>
                        <label class="field-group"><span>{{ __('ui.home.experience') }}</span><input type="text" name="experience" x-model="form.experience" required maxlength="40"></label>
                        <label class="field-group"><span>{{ __('ui.home.drop') }}</span><input type="text" name="drop" x-model="form.drop" required maxlength="40"></label>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="field-group"><span>{{ __('ui.admin.server_capacity') }}</span><input type="number" name="capacity" x-model.number="form.capacity" min="1" max="100000" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.download_sort_order') }}</span><input type="number" name="sort_order" x-model.number="form.sort_order" min="0" max="10000" required></label>
                    </div>
                    <label class="field-group" x-show="!form.is_current"><span>{{ __('ui.admin.server_website_url') }}</span><input type="url" name="website_url" x-model="form.website_url" maxlength="2048" x-bind:required="!form.is_current" placeholder="https://hard.dreammu.example"></label>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-black/20 p-4 text-sm text-silver">
                            <input type="hidden" name="is_current" value="0"><input type="checkbox" name="is_current" value="1" x-model="form.is_current" class="size-4 accent-amber-400">{{ __('ui.admin.server_current') }}
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-black/20 p-4 text-sm text-silver">
                            <input type="hidden" name="is_visible" value="0"><input type="checkbox" name="is_visible" value="1" x-model="form.is_visible" class="size-4 accent-amber-400">{{ __('ui.admin.server_visible') }}
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-black/20 p-4 text-sm text-silver">
                            <input type="hidden" name="show_online_status" value="0"><input type="checkbox" name="show_online_status" value="1" x-model="form.show_online_status" class="size-4 accent-amber-400">{{ __('ui.admin.server_show_online_status') }}
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-white/10 bg-black/20 p-4 text-sm text-silver">
                            <input type="hidden" name="show_online_users" value="0"><input type="checkbox" name="show_online_users" value="1" x-model="form.show_online_users" class="size-4 accent-amber-400">{{ __('ui.admin.server_show_online_users') }}
                        </label>
                    </div>

                    <p class="text-sm text-steel">{{ __('ui.admin.server_form_help') }}</p>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" class="button button-ghost" x-on:click="closeEditor()">{{ __('ui.admin.news_cancel') }}</button>
                        <button type="submit" class="button button-primary">{{ __('ui.admin.server_save') }}</button>
                    </div>
                </form>
                </section>
            </div>
        </template>

        <template x-teleport="body">
            <div x-cloak x-show="deleteOpen" class="fixed inset-0 z-[120] grid place-items-center bg-black/80 p-4 backdrop-blur-sm" x-on:click.self="closeDelete()" role="dialog" aria-modal="true">
                <section class="panel-card w-full max-w-lg p-6 sm:p-8">
                <p class="kicker">{{ __('ui.admin.news_delete') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white" x-text="form.name"></h2>
                <p class="mt-3 text-sm text-silver">{{ __('ui.admin.server_delete_help') }}</p>
                <form method="POST" x-bind:action="deleteAction" class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="button button-ghost" x-on:click="closeDelete()">{{ __('ui.admin.news_cancel') }}</button>
                    <button type="submit" class="button button-primary">{{ __('ui.admin.server_delete_confirm') }}</button>
                </form>
                </section>
            </div>
        </template>
    </div>
@endsection
