@extends('layouts.admin')

@section('title', __('ui.admin.character_rule_profiles.'.$profile.'.title'))

@section('content')
    <div class="admin-shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.character_rules') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.character_rule_profiles.'.$profile.'.heading') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.character_rule_profiles.'.$profile.'.intro') }}</p>
        </div>

        <div class="mt-8">
            <main class="grid min-w-0 content-start gap-6 2xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="grid content-start gap-6">
                    @if (session('status'))
                        <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                            <p class="font-semibold">{{ __('ui.admin.character_rule_profile_failed') }}</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.character-rules.update', ['profile' => $profile]) }}" class="panel-card p-5 sm:p-7">
                        @csrf
                        @method('PUT')

                        @include('admin.partials.character-rules.'.$profile)

                        <div class="mt-8 flex flex-col gap-3 border-t border-white/8 pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-steel">{{ __('ui.admin.character_rule_applies_immediately') }}</p>
                            <button type="submit" class="button button-primary">{{ __('ui.admin.save_and_apply') }}</button>
                        </div>
                    </form>
                </div>

                <aside class="grid content-start gap-4">
                    <section class="rounded-2xl border border-emerald-300/15 bg-emerald-300/6 p-4 text-sm leading-6 text-emerald-100">
                        <strong class="block text-emerald-200">{{ __('ui.admin.active_configuration') }}</strong>
                        {{ __('ui.admin.active_configuration_help') }}
                    </section>

                    <x-admin-revision-history
                        :revisions="$revisions"
                        :title="__('ui.admin.revision_history')"
                        :empty-message="__('ui.admin.no_revisions')"
                    />
                </aside>
            </main>
        </div>
    </div>
@endsection
