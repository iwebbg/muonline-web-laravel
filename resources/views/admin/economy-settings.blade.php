@extends('layouts.app')

@section('title', __('ui.admin.economy_settings'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div>
            <div class="eyebrow mb-4">{{ __('ui.admin.economy') }}</div>
            <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.economy_settings') }}</h1>
            <p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.economy_settings_intro') }}</p>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation active="economy-settings" />

            <main class="min-w-0">
                @if (session('status'))
                    <div class="mb-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.economy-settings.update') }}" class="panel-card p-5 sm:p-7">
                    @csrf
                    @method('PUT')

                    <section>
                        <p class="kicker">{{ __('ui.admin.item_generator_pricing') }}</p>
                        <label class="field-group mt-5 max-w-xl">
                            <span>{{ __('ui.admin.item_generator_markup') }}</span>
                            <input type="number" name="itemGeneratorMarkupPercentage" value="{{ old('itemGeneratorMarkupPercentage', $settings['itemGeneratorMarkupPercentage']) }}" min="0" max="10000" required>
                            <small>{{ __('ui.admin.item_generator_markup_help') }}</small>
                        </label>
                    </section>

                    <section class="mt-8 border-t border-white/8 pt-7">
                        <p class="kicker">{{ __('ui.admin.character_paid_services') }}</p>
                        <div class="mt-5 grid gap-6 md:grid-cols-2">
                            @foreach ([
                                'characterRename' => ['character_rename', 'character_rename_help'],
                                'characterClassChange' => ['character_class_change', 'character_class_change_help'],
                            ] as $prefix => [$label, $help])
                                <div class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                    <label class="toggle-control">
                                        <input type="hidden" name="{{ $prefix }}Enabled" value="0">
                                        <input type="checkbox" name="{{ $prefix }}Enabled" value="1" @checked(old($prefix.'Enabled', $settings[$prefix.'Enabled']))>
                                        <span aria-hidden="true"></span><span>{{ __('ui.admin.'.$label) }}</span>
                                    </label>
                                    <label class="field-group mt-4">
                                        <span>{{ __('ui.admin.price_credits') }}</span>
                                        <input type="number" name="{{ $prefix }}PriceCredits" value="{{ old($prefix.'PriceCredits', $settings[$prefix.'PriceCredits']) }}" min="0" max="9000000000000000" required>
                                        <small>{{ __('ui.admin.'.$help) }}</small>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <div class="mt-8 flex flex-col gap-3 border-t border-white/8 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-steel">{{ __('ui.admin.current_revision', ['revision' => $revision]) }}</p>
                        <button type="submit" class="button button-primary">{{ __('ui.admin.save_and_apply') }}</button>
                    </div>
                </form>
            </main>
        </div>
    </div>
@endsection
