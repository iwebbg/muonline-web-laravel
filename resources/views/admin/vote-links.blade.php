@extends('layouts.app')

@section('title', __('ui.admin.vote_links_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.vote_links_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.vote_links_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="vote-links" />

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

                <section class="panel-card p-5 sm:p-7">
                    <p class="kicker">{{ __('ui.admin.vote_site_new') }}</p>
                    <form method="POST" action="{{ route('admin.vote-sites.store') }}" class="mt-5 grid gap-5">
                        @csrf
                        @include('admin.partials.vote-site-fields', ['site' => null])
                        <div class="flex justify-end">
                            <button type="submit" class="button button-primary">{{ __('ui.admin.vote_site_create') }}</button>
                        </div>
                    </form>
                </section>

                <section class="grid gap-4">
                    @forelse ($sites as $site)
                        <article class="panel-card p-5 sm:p-7" x-data="{ editing: false }">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-semibold text-white">{{ $site->name_en }}</h2>
                                    <p class="mt-2 break-all text-sm text-steel">{{ $site->url }}</p>
                                    <p class="mt-2 text-sm text-silver">
                                        {{ number_format($site->reward_credits) }} Credits · {{ number_format($site->reward_zen) }} Zen · {{ $site->cooldown_hours }}h
                                    </p>
                                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.12em] {{ $site->auto_reward_on_click ? 'text-emerald-200' : 'text-amber-200' }}">
                                        {{ $site->auto_reward_on_click ? __('ui.admin.vote_site_mode_automatic') : __('ui.admin.vote_site_mode_manual') }}
                                    </p>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" class="button button-ghost" x-on:click="editing = ! editing">{{ __('ui.admin.vote_site_edit') }}</button>
                                    <form method="POST" action="{{ route('admin.vote-sites.destroy', $site) }}" onsubmit="return confirm(@js(__('ui.admin.vote_site_delete_confirm')))" >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button button-ghost text-red-200">{{ __('ui.admin.vote_site_delete') }}</button>
                                    </form>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.vote-sites.update', $site) }}" class="mt-6 grid gap-5 border-t border-white/8 pt-6" x-show="editing" x-cloak>
                                @csrf
                                @method('PUT')
                                @include('admin.partials.vote-site-fields', ['site' => $site])
                                <div class="flex justify-end">
                                    <button type="submit" class="button button-primary">{{ __('ui.admin.vote_site_save') }}</button>
                                </div>
                            </form>
                        </article>
                    @empty
                        <div class="panel-card p-8 text-center text-steel">{{ __('ui.admin.vote_sites_empty') }}</div>
                    @endforelse
                </section>
            </main>
        </div>
    </div>
@endsection
