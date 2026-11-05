@extends('layouts.app')

@section('title', __('ui.admin.credit_packages_title'))

@section('content')
    <div
        class="shell py-10 lg:py-14"
        x-data="creditPackageManager(@js([
            'createUrl' => route('admin.credit-packages.store'),
            'defaultCurrency' => config('services.payments.currency'),
        ]))"
        x-on:keydown.escape.window="closeEditor()"
    >
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="kicker">{{ __('ui.admin.economy') }}</p>
                <h1 class="mt-3 font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.credit_packages_heading') }}</h1>
                <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.credit_packages_intro') }}</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="panel-card px-5 py-3 text-sm text-silver">
                    {{ __('ui.admin.credit_providers') }}:
                    @if ($enabledProviders === [])
                        {{ __('ui.admin.credit_none') }}
                    @else
                        @foreach ($enabledProviders as $provider)
                            {{ ucfirst($provider->value) }}@if (! $loop->last), @endif
                        @endforeach
                    @endif
                </div>
                <button type="button" class="button button-primary" x-on:click="openCreate()">
                    {{ __('ui.admin.credit_package_create') }}
                </button>
            </div>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[18rem_minmax(0,1fr)]">
            <x-admin-navigation active="credit-packages" />

            <main class="grid min-w-0 content-start gap-6">
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

                <form method="GET" action="{{ route('admin.credit-packages.index') }}" class="panel-card grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_14rem_auto] md:items-end">
                    <label class="field-group">
                        <span>{{ __('ui.admin.credit_package_search') }}</span>
                        <input type="search" name="search" value="{{ $packageFilters['search'] }}" maxlength="120" placeholder="{{ __('ui.admin.credit_package_search_placeholder') }}">
                    </label>
                    <label class="field-group">
                        <span>{{ __('ui.admin.credit_status') }}</span>
                        <select name="status">
                            <option value="">{{ __('ui.admin.credit_all_statuses') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($packageFilters['status'] === $status->value)>
                                    {{ __('ui.admin.credit_statuses.'.$status->value) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="button button-primary">{{ __('ui.admin.credit_package_filter') }}</button>
                        <a href="{{ route('admin.credit-packages.index') }}" class="button button-ghost">{{ __('ui.admin.credit_package_clear') }}</a>
                    </div>
                </form>

                <section class="grid gap-4">
                    @forelse ($packages as $package)
                        <article class="panel-card flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span @class([
                                        'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                        'border-amber-300/25 bg-amber-300/8 text-amber-100' => $package->status === \App\Payments\CreditPackageStatus::Draft,
                                        'border-emerald-300/25 bg-emerald-300/8 text-emerald-100' => $package->status === \App\Payments\CreditPackageStatus::Published,
                                        'border-white/10 bg-white/5 text-steel' => $package->status === \App\Payments\CreditPackageStatus::Archived,
                                    ])>
                                        {{ __('ui.admin.credit_statuses.'.$package->status->value) }}
                                    </span>
                                    <span class="text-xs uppercase tracking-[0.14em] text-steel">
                                        {{ __('ui.admin.credit_sort') }}: {{ $package->sort_order }}
                                    </span>
                                </div>
                                <h2 class="mt-3 truncate text-xl font-semibold text-white">{{ $package->title_en }}</h2>
                                <p class="mt-1 truncate text-sm text-steel">{{ $package->title_bg }}</p>
                                <p class="mt-3 text-sm text-silver">
                                    {{ number_format($package->credits) }} Credits · {{ number_format($package->price_minor / 100, 2) }} {{ $package->currency }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="button button-ghost shrink-0"
                                x-on:click="openEdit(@js([
                                    'title_en' => $package->title_en,
                                    'title_bg' => $package->title_bg,
                                    'credits' => $package->credits,
                                    'price' => number_format($package->price_minor / 100, 2, '.', ''),
                                    'currency' => $package->currency,
                                    'status' => $package->status->value,
                                    'sort_order' => $package->sort_order,
                                    'update_url' => route('admin.credit-packages.update', $package),
                                ]))"
                            >
                                {{ __('ui.admin.credit_package_edit') }}
                            </button>
                        </article>
                    @empty
                        <div class="panel-card p-10 text-center text-steel">{{ __('ui.admin.credit_packages_empty') }}</div>
                    @endforelse
                </section>

                @if ($packages->hasPages())
                    <div class="panel-card p-5">{{ $packages->links() }}</div>
                @endif
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
                aria-labelledby="credit-package-editor-title"
            >
                <section class="panel-card mx-auto w-full max-w-3xl p-5 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="kicker">{{ __('ui.admin.credit_packages_heading') }}</p>
                            <h2
                                id="credit-package-editor-title"
                                class="mt-2 text-2xl font-semibold text-white"
                                x-text="editing ? @js(__('ui.admin.credit_package_edit')) : @js(__('ui.admin.credit_package_create'))"
                            ></h2>
                        </div>
                        <button type="button" class="icon-button" x-on:click="closeEditor()" aria-label="{{ __('ui.admin.credit_package_close') }}">×</button>
                    </div>

                    <form method="POST" x-bind:action="formAction" class="mt-6 grid gap-5">
                        @csrf
                        <input x-show="editing" type="hidden" name="_method" value="PUT" x-bind:disabled="!editing">

                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="field-group"><span>{{ __('ui.admin.credit_title_en') }}</span><input x-ref="packageTitle" name="title_en" x-model="form.title_en" required maxlength="120"></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_title_bg') }}</span><input name="title_bg" x-model="form.title_bg" required maxlength="120"></label>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="field-group"><span>{{ __('ui.admin.credit_amount') }}</span><input type="number" name="credits" x-model.number="form.credits" min="1" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_price') }}</span><input type="number" step="0.01" name="price" x-model="form.price" min="0.01" required></label>
                        </div>
                        <div class="grid gap-5 md:grid-cols-3">
                            <label class="field-group"><span>{{ __('ui.admin.credit_currency') }}</span><input name="currency" x-model="form.currency" minlength="3" maxlength="3" required></label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.credit_status') }}</span>
                                <select name="status" x-model="form.status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}">{{ __('ui.admin.credit_statuses.'.$status->value) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_sort') }}</span><input type="number" name="sort_order" x-model.number="form.sort_order" min="0" required></label>
                        </div>
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="button button-ghost" x-on:click="closeEditor()">{{ __('ui.admin.credit_package_cancel') }}</button>
                            <button type="submit" class="button button-primary">{{ __('ui.admin.credit_save') }}</button>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    </div>
@endsection
