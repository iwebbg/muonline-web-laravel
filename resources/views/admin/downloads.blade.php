@extends('layouts.admin')

@section('title', __('ui.admin.download_title'))

@section('content')
    <div
        class="admin-shell py-10 lg:py-14"
        x-data="downloadManager(@js($downloads), @js(route('admin.downloads.store')))"
        x-on:keydown.escape.window="editorOpen ? closeEditor() : closeDelete()"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.download_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.download_intro') }}</p>
            </div>
            <button type="button" class="button button-primary" x-on:click="openCreate()">{{ __('ui.admin.download_create') }}</button>
        </div>

        <div class="mt-8">
            <main class="grid min-w-0 content-start gap-4">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
                @endif

                @forelse ($downloads as $download)
                    <article class="panel-card flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border border-sky-300/25 bg-sky-300/8 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.12em] text-sky-200">{{ __('ui.downloads.categories.'.$download['category']) }}</span>
                                <span class="text-xs text-steel">{{ __('ui.admin.news_status_'.$download['status']) }}</span>
                                <span class="text-xs text-steel">{{ __('ui.admin.news_revisions', ['count' => $download['revisions_count']]) }}</span>
                            </div>
                            <h2 class="mt-3 truncate text-xl font-semibold text-white">{{ $download['title_en'] }}</h2>
                            <p class="mt-1 truncate text-sm text-steel">{{ $download['download_url'] }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" class="button button-ghost" x-on:click="openEdit(@js($download))">{{ __('ui.admin.news_edit') }}</button>
                            <button type="button" class="button button-ghost text-red-200" x-on:click="openDelete(@js($download))">{{ __('ui.admin.news_delete') }}</button>
                        </div>
                    </article>
                @empty
                    <article class="panel-card py-14 text-center">
                        <p class="kicker">{{ __('ui.admin.download_empty') }}</p>
                        <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.admin.download_empty_help') }}</p>
                    </article>
                @endforelse
            </main>
        </div>

        <template x-teleport="body">
            <div x-cloak x-show="editorOpen" class="fixed inset-0 z-[120] overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-8" x-on:click.self="closeEditor()" role="dialog" aria-modal="true" aria-labelledby="download-editor-title">
                <section class="panel-card mx-auto w-full max-w-5xl p-5 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="kicker">{{ __('ui.admin.downloads') }}</p>
                        <h2 id="download-editor-title" class="mt-2 text-2xl font-semibold text-white" x-text="editing ? @js(__('ui.admin.download_edit_heading')) : @js(__('ui.admin.download_create_heading'))"></h2>
                    </div>
                    <button type="button" class="icon-button" x-on:click="closeEditor()" aria-label="{{ __('ui.admin.news_close') }}">×</button>
                </div>

                <form method="POST" x-bind:action="formAction" class="mt-6 grid gap-5">
                    @csrf
                    <input x-show="editing" type="hidden" name="_method" value="PUT" x-bind:disabled="!editing">

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="field-group"><span>{{ __('ui.admin.news_title_en') }}</span><input x-ref="downloadTitle" type="text" name="title_en" x-model="form.title_en" required maxlength="160"></label>
                        <label class="field-group"><span>{{ __('ui.admin.news_title_bg') }}</span><input type="text" name="title_bg" x-model="form.title_bg" maxlength="160"></label>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="field-group"><span>{{ __('ui.admin.news_slug') }}</span><input type="text" name="slug" x-model="form.slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label>
                        <label class="field-group"><span>{{ __('ui.admin.download_category') }}</span><select name="category" x-model="form.category">@foreach (['client', 'patch', 'tool', 'other'] as $category)<option value="{{ $category }}">{{ __('ui.downloads.categories.'.$category) }}</option>@endforeach</select></label>
                    </div>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="field-group"><span>{{ __('ui.admin.download_description_en') }}</span><textarea name="description_en" x-model="form.description_en" required maxlength="5000" rows="5"></textarea></label>
                        <label class="field-group"><span>{{ __('ui.admin.download_description_bg') }}</span><textarea name="description_bg" x-model="form.description_bg" maxlength="5000" rows="5"></textarea></label>
                    </div>
                    <label class="field-group"><span>{{ __('ui.admin.download_url') }}</span><input type="url" name="download_url" x-model="form.download_url" required maxlength="2048" placeholder="https://downloads.example.com/client.zip"></label>
                    <div class="grid gap-5 md:grid-cols-3">
                        <label class="field-group"><span>{{ __('ui.admin.download_version') }}</span><input type="text" name="version" x-model="form.version" maxlength="80"></label>
                        <label class="field-group"><span>{{ __('ui.admin.download_file_size') }}</span><input type="text" name="file_size" x-model="form.file_size" maxlength="40" placeholder="850 MB"></label>
                        <label class="field-group"><span>{{ __('ui.admin.download_sort_order') }}</span><input type="number" name="sort_order" x-model.number="form.sort_order" min="0" max="10000" required></label>
                    </div>
                    <label class="field-group"><span>{{ __('ui.admin.download_checksum') }}</span><input type="text" name="checksum_sha256" x-model="form.checksum_sha256" maxlength="64" pattern="[a-fA-F0-9]{64}"></label>
                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="field-group"><span>{{ __('ui.admin.news_status') }}</span><select name="status" x-model="form.status"><option value="draft">{{ __('ui.admin.news_status_draft') }}</option><option value="published">{{ __('ui.admin.news_status_published') }}</option></select></label>
                        <label class="field-group" x-show="form.status === 'published'"><span>{{ __('ui.admin.news_publish_at') }}</span><input type="datetime-local" name="published_at" x-model="form.published_at"></label>
                    </div>
                    <p class="text-sm text-steel">{{ __('ui.admin.download_security_help') }}</p>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" class="button button-ghost" x-on:click="closeEditor()">{{ __('ui.admin.news_cancel') }}</button>
                        <button type="submit" class="button button-primary">{{ __('ui.admin.download_save') }}</button>
                    </div>
                </form>
                </section>
            </div>
        </template>

        <template x-teleport="body">
            <div x-cloak x-show="deleteOpen" class="fixed inset-0 z-[120] grid place-items-center bg-black/80 p-4 backdrop-blur-sm" x-on:click.self="closeDelete()" role="dialog" aria-modal="true">
                <section class="panel-card w-full max-w-lg p-6 sm:p-8">
                <p class="kicker">{{ __('ui.admin.news_delete') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white" x-text="form.title_en"></h2>
                <p class="mt-3 text-sm text-silver">{{ __('ui.admin.download_delete_help') }}</p>
                <form method="POST" x-bind:action="deleteAction" class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="button button-ghost" x-on:click="closeDelete()">{{ __('ui.admin.news_cancel') }}</button>
                    <button type="submit" class="button button-primary">{{ __('ui.admin.download_delete_confirm') }}</button>
                </form>
                </section>
            </div>
        </template>
    </div>
@endsection
