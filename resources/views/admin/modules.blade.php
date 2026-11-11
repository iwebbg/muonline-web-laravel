@extends('layouts.admin')

@section('title', __('ui.admin.modules_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.modules_heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.modules_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="modules" />

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
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="kicker">{{ __('ui.admin.modules_profile') }}</p>
                            <h2 class="mt-2 text-xl font-semibold text-white">{{ $gameVersion }}</h2>
                            <p class="mt-1 text-sm text-steel">{{ __('ui.admin.revision') }} {{ $revision }}</p>
                        </div>
                        <span class="resource-chip">{{ __('ui.admin.modules_server_authoritative') }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.modules.update') }}" class="mt-6 grid gap-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="revision" value="{{ $revision }}">

                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($moduleCards as $module)
                                <label class="flex cursor-pointer items-start gap-4 rounded-2xl border border-white/8 bg-black/20 p-5 transition hover:border-gold/30">
                                    <input
                                        type="checkbox"
                                        name="modules[{{ $module['key'] }}]"
                                        value="1"
                                        class="mt-1"
                                        @checked(old('modules.'.$module['key'], $module['enabled']))
                                    >
                                    <span>
                                        <span class="block font-semibold text-white">{{ __('ui.admin.module_labels.'.$module['key']) }}</span>
                                        <span class="mt-1 block text-sm leading-6 text-steel">{{ __('ui.admin.module_descriptions.'.$module['key']) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-3 border-t border-white/8 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-steel">{{ __('ui.admin.modules_disabled_help') }}</p>
                            <button type="submit" class="button button-primary">{{ __('ui.admin.modules_save') }}</button>
                        </div>
                    </form>
                </section>

                <x-admin-revision-history
                    :revisions="$recentRevisions"
                    :title="__('ui.admin.modules_history')"
                    :empty-message="__('ui.admin.modules_no_history')"
                    wide
                />
            </main>
        </div>
    </div>
@endsection
