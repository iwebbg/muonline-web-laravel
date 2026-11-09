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

            <main class="grid min-w-0 content-start gap-6 2xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="grid content-start gap-6">
                    @if (session('status'))
                        <div class="rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">{{ $errors->first() }}</div>
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
                                ['characterRenameEnabled', 'characterRenamePriceCredits', 'character_rename', 'character_rename_help'],
                                ['characterClassChangeEnabled', 'characterClassChangePriceCredits', 'character_class_change', 'character_class_change_help'],
                                ['characterResetPurchaseEnabled', 'characterResetPriceCredits', 'character_reset_purchase', 'character_reset_purchase_help'],
                            ] as [$enabledKey, $priceKey, $label, $help])
                                <div class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                    <label class="toggle-control">
                                        <input type="hidden" name="{{ $enabledKey }}" value="0">
                                        <input type="checkbox" name="{{ $enabledKey }}" value="1" @checked(old($enabledKey, $settings[$enabledKey]))>
                                        <span aria-hidden="true"></span><span>{{ __('ui.admin.'.$label) }}</span>
                                    </label>
                                    <label class="field-group mt-4">
                                        <span>{{ __('ui.admin.price_credits') }}</span>
                                        <input type="number" name="{{ $priceKey }}" value="{{ old($priceKey, $settings[$priceKey]) }}" min="0" max="9000000000000000" required>
                                        <small>{{ __('ui.admin.'.$help) }}</small>
                                    </label>
                                </div>
                            @endforeach
                            <div class="rounded-2xl border border-white/8 bg-black/15 p-4">
                                <label class="toggle-control">
                                    <input type="hidden" name="characterLevelUpPointsPurchaseEnabled" value="0">
                                    <input type="checkbox" name="characterLevelUpPointsPurchaseEnabled" value="1" @checked(old('characterLevelUpPointsPurchaseEnabled', $settings['characterLevelUpPointsPurchaseEnabled']))>
                                    <span aria-hidden="true"></span><span>{{ __('ui.admin.character_level_up_points_purchase') }}</span>
                                </label>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <label class="field-group">
                                        <span>{{ __('ui.admin.points_per_package') }}</span>
                                        <input type="number" name="characterLevelUpPointsPackageSize" value="{{ old('characterLevelUpPointsPackageSize', $settings['characterLevelUpPointsPackageSize']) }}" min="1" max="1000000" required>
                                    </label>
                                    <label class="field-group">
                                        <span>{{ __('ui.admin.price_credits') }}</span>
                                        <input type="number" name="characterLevelUpPointsPriceCredits" value="{{ old('characterLevelUpPointsPriceCredits', $settings['characterLevelUpPointsPriceCredits']) }}" min="0" max="9000000000000000" required>
                                    </label>
                                </div>
                                <p class="mt-3 text-xs leading-5 text-steel">{{ __('ui.admin.character_level_up_points_purchase_help') }}</p>
                            </div>
                        </div>
                    </section>

                    <div class="mt-8 flex flex-col gap-3 border-t border-white/8 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-steel">{{ __('ui.admin.current_revision', ['revision' => $revision]) }}</p>
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
                        :revisions="$recentRevisions"
                        :title="__('ui.admin.revision_history')"
                        :empty-message="__('ui.admin.no_revisions')"
                    />
                </aside>
            </main>
        </div>
    </div>
@endsection
