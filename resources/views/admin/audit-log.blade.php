@extends('layouts.admin')

@section('title', __('ui.admin.audit_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.audit_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.audit_intro') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="resource-chip">
                    <span>{{ __('ui.admin.signed_in_as') }}</span>
                    <strong>{{ auth('admin')->user()->name }}</strong>
                </span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="button button-ghost">{{ __('ui.navigation.logout') }}</button>
                </form>
            </div>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="audit-log" />

            <main class="grid min-w-0 content-start gap-6">
                @if ($errors->any())
                    <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="GET" action="{{ route('admin.audit-log') }}" class="panel-card grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(15rem,1fr)_12rem_10rem_10rem_10rem_auto] xl:items-end">
                    <label class="field-group">
                        <span>{{ __('ui.admin.audit_search') }}</span>
                        <input
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            maxlength="80"
                            placeholder="{{ __('ui.admin.audit_search_placeholder') }}"
                        >
                    </label>
                    <label class="field-group">
                        <span>{{ __('ui.admin.audit_type') }}</span>
                        <select name="type">
                            <option value="">{{ __('ui.admin.audit_all_types') }}</option>
                            @foreach ($supportedTypes as $type)
                                <option value="{{ $type }}" @selected($selectedType === $type)>
                                    {{ __('ui.admin.audit_types.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field-group">
                        <span>{{ __('ui.admin.audit_status') }}</span>
                        <select name="status">
                            <option value="">{{ __('ui.admin.audit_all_statuses') }}</option>
                            @foreach ($supportedStatuses as $status)
                                <option value="{{ $status }}" @selected($selectedStatus === $status)>
                                    {{ __('ui.admin.audit_statuses.'.$status) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field-group">
                        <span>{{ __('ui.admin.date_from') }}</span>
                        <input type="date" name="date_from" value="{{ $dateFrom }}">
                    </label>
                    <label class="field-group">
                        <span>{{ __('ui.admin.date_to') }}</span>
                        <input type="date" name="date_to" value="{{ $dateTo }}">
                    </label>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="button button-primary">{{ __('ui.admin.audit_filter') }}</button>
                        <a href="{{ route('admin.audit-log') }}" class="button button-ghost">{{ __('ui.admin.audit_clear') }}</a>
                    </div>
                </form>

                <section class="grid gap-4" aria-label="{{ __('ui.admin.audit_results') }}">
                    @forelse ($auditEntries as $entry)
                        <article class="panel-card p-5 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="kicker">{{ __('ui.admin.audit_types.'.$entry['type']) }}</span>
                                        <span @class([
                                            'rounded-full border px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.12em]',
                                            'border-emerald-300/25 bg-emerald-300/8 text-emerald-200' => $entry['status'] === 'completed',
                                            'border-red-300/25 bg-red-300/8 text-red-200' => $entry['status'] === 'failed',
                                            'border-amber-300/25 bg-amber-300/8 text-amber-200' => $entry['status'] === 'pending',
                                        ])>
                                            {{ __('ui.admin.audit_statuses.'.$entry['status']) }}
                                        </span>
                                    </div>
                                    <p class="mt-3 truncate text-lg font-semibold text-white">
                                        {{ $entry['subject'] }}
                                        @if ($entry['characterName'] !== null)
                                            <span class="text-steel">·</span> {{ $entry['characterName'] }}
                                        @endif
                                    </p>
                                    @if ($entry['failureCode'] !== null)
                                        <p class="mt-2 text-sm text-red-200">
                                            {{ __('ui.admin.audit_failure') }}: {{ $entry['failureCode'] }}
                                        </p>
                                    @endif
                                </div>
                                <time class="shrink-0 text-sm text-steel" datetime="{{ $entry['occurredAt'] }}">
                                    {{ $entry['occurredAtDisplay'] }}
                                </time>
                            </div>

                            <details class="mt-5 rounded-2xl border border-white/8 bg-black/20 p-4">
                                <summary class="cursor-pointer text-sm font-semibold text-gold">
                                    {{ __('ui.admin.audit_inspect') }}
                                </summary>
                                <div class="mt-4 grid gap-4 xl:grid-cols-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-steel">{{ __('ui.admin.audit_context') }}</p>
                                        <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap rounded-xl bg-black/35 p-3 text-xs leading-5 text-silver">{{ json_encode($entry['contextSnapshot'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-steel">{{ __('ui.admin.audit_before') }}</p>
                                        <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap rounded-xl bg-black/35 p-3 text-xs leading-5 text-silver">{{ json_encode($entry['beforeSnapshot'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-steel">{{ __('ui.admin.audit_after') }}</p>
                                        <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap rounded-xl bg-black/35 p-3 text-xs leading-5 text-silver">{{ json_encode($entry['afterSnapshot'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                </div>
                            </details>
                        </article>
                    @empty
                        <article class="panel-card py-14 text-center">
                            <p class="kicker">{{ __('ui.admin.audit_empty') }}</p>
                            <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.admin.audit_empty_help') }}</p>
                        </article>
                    @endforelse
                </section>

                @if ($auditEntries->hasPages())
                    <div class="panel-card p-4 sm:p-5">
                        {{ $auditEntries->links() }}
                    </div>
                @endif
            </main>
        </div>
    </div>
@endsection
