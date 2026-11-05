@props([
    'revisions',
    'title',
    'emptyMessage',
    'wide' => false,
])

<section x-data="{ selectedRevision: null }" class="panel-card p-4 sm:p-5">
    <p class="kicker">{{ $title }}</p>

    <div @class([
        'mt-4 grid gap-2',
        'sm:grid-cols-2 xl:grid-cols-3' => $wide,
    ])>
        @forelse ($revisions as $revisionEntry)
            <button
                type="button"
                class="w-full rounded-xl border border-white/8 bg-black/20 px-3 py-2 text-left transition hover:border-gold/35 hover:bg-gold/5 focus-visible:border-gold"
                x-on:click="selectedRevision = @js($revisionEntry)"
            >
                <span class="flex items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-white">{{ __('ui.admin.revision') }} {{ $revisionEntry['revision'] }}</span>
                    <span class="text-xs text-gold">{{ __('ui.admin.revision_view_changes') }} →</span>
                </span>
                @if (($revisionEntry['context'] ?? '') !== '')
                    <span class="mt-1 block truncate text-xs text-silver">{{ $revisionEntry['context'] }}</span>
                @endif
                <span class="mt-1 block text-xs text-steel">{{ $revisionEntry['createdAt'] }}</span>
            </button>
        @empty
            <p class="text-sm text-steel">{{ $emptyMessage }}</p>
        @endforelse
    </div>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="selectedRevision !== null"
            x-on:keydown.escape.window="selectedRevision = null"
            x-on:click.self="selectedRevision = null"
            class="fixed inset-0 z-[100] flex min-h-full items-center overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-8"
            role="dialog"
            aria-modal="true"
            aria-labelledby="admin-revision-title"
        >
            <div class="panel-card mx-auto my-auto w-full max-w-3xl p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="kicker">{{ __('ui.admin.revision_changes') }}</p>
                        <h2 id="admin-revision-title" class="mt-2 text-2xl font-semibold text-white">
                            {{ __('ui.admin.revision') }} <span x-text="selectedRevision?.revision"></span>
                        </h2>
                        <p x-show="selectedRevision?.context" class="mt-1 text-sm text-silver" x-text="selectedRevision?.context"></p>
                        <p class="mt-1 text-sm text-steel" x-text="selectedRevision?.createdAt"></p>
                    </div>
                    <button type="button" class="icon-button" x-on:click="selectedRevision = null" aria-label="{{ __('ui.admin.close_revision') }}">×</button>
                </div>

                <div class="mt-6 overflow-x-auto rounded-2xl border border-white/8">
                    <div class="grid min-w-[36rem] grid-cols-[minmax(0,1fr)_minmax(7rem,0.7fr)_minmax(7rem,0.7fr)] gap-4 border-b border-white/8 bg-white/[0.03] px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-steel">
                        <span>{{ __('ui.admin.revision_field') }}</span>
                        <span>{{ __('ui.admin.revision_before') }}</span>
                        <span>{{ __('ui.admin.revision_after') }}</span>
                    </div>
                    <template x-for="change in selectedRevision?.changes ?? []" :key="change.label">
                        <div class="grid min-w-[36rem] grid-cols-[minmax(0,1fr)_minmax(7rem,0.7fr)_minmax(7rem,0.7fr)] gap-4 border-b border-white/8 px-4 py-4 text-sm last:border-b-0">
                            <strong class="text-silver" x-text="change.label"></strong>
                            <span class="break-words text-steel line-through decoration-red-300/60" x-text="change.before"></span>
                            <span class="break-words font-semibold text-emerald-200" x-text="change.after"></span>
                        </div>
                    </template>
                    <p x-show="selectedRevision?.changes.length === 0" class="px-4 py-6 text-sm text-steel">
                        {{ __('ui.admin.revision_no_changes') }}
                    </p>
                </div>
            </div>
        </div>
    </template>
</section>
