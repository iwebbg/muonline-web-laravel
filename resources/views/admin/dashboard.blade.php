@extends('layouts.app')

@section('title', __('ui.admin.title'))

@section('content')
    <div
        class="shell py-10 lg:py-14"
        x-data="resetSettings(
            {{ Js::from($resetSettings) }},
            {{ Js::from([
                'statsReset' => __('ui.admin.stats_reset'),
                'preserved' => __('ui.admin.preserved'),
                'keepAll' => __('ui.admin.keep_all'),
                'clearBag' => __('ui.admin.clear_bag'),
                'clearAll' => __('ui.admin.clear_all'),
                'save' => __('ui.admin.save_draft'),
                'saving' => __('ui.admin.saving'),
                'saved' => __('ui.admin.draft_saved'),
                'saveFailed' => __('ui.admin.save_failed'),
                'revision' => __('ui.admin.revision'),
                'justNow' => __('ui.admin.just_now'),
            ]) }},
            {{ Js::from([
                'saveUrl' => route('admin.reset-profile.update'),
                'csrfToken' => csrf_token(),
                'revision' => $resetRevision,
                'revisions' => $resetRevisions,
            ]) }}
        )"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.admin.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.heading') }}</h1>
                <p class="mt-3 max-w-2xl text-silver">{{ __('ui.admin.intro') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="resource-chip">
                    <span>{{ __('ui.admin.signed_in_as') }}</span>
                    <strong>{{ auth('admin')->user()->name }}</strong>
                </span>
                <span class="status-pill"><span class="status-dot"></span>{{ __('ui.admin.services_healthy') }}</span>
                <button
                    type="submit"
                    form="reset-profile-form"
                    class="button button-primary"
                    :disabled="saving"
                    x-text="saving ? translations.saving : translations.save"
                >{{ __('ui.admin.save_draft') }}</button>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="button button-ghost">{{ __('ui.navigation.logout') }}</button>
                </form>
            </div>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-[16rem_minmax(0,1fr)]">
            <x-admin-navigation
                active="character-rules"
                :reset-revision="$resetRevision"
            />

            <div id="character-rules" class="grid min-w-0 scroll-mt-28 gap-6 2xl:grid-cols-[minmax(0,1fr)_22rem]">
                <form
                    id="reset-profile-form"
                    method="POST"
                    action="{{ route('admin.reset-profile.update') }}"
                    class="panel-card"
                    @submit.prevent="saveSettings"
                >
                    @csrf
                    @method('PUT')

                    <div class="flex items-start justify-between gap-4 border-b border-white/8 pb-6">
                        <div>
                            <p class="kicker">{{ __('ui.admin.character_rules') }}</p>
                            <h2 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.admin.reset_profile') }}</h2>
                            <p class="mt-2 text-sm text-steel">{{ __('ui.admin.version_aware') }}</p>
                        </div>
                        <label class="toggle-control">
                            <input type="checkbox" name="enabled" value="1" x-model="settings.enabled">
                            <span aria-hidden="true"></span>
                            <span>{{ __('ui.admin.enabled') }}</span>
                        </label>
                    </div>

                    <template x-if="Object.keys(validationErrors).length > 0">
                        <div class="mt-6 rounded-xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100" role="alert">
                            {{ __('ui.admin.save_failed') }}
                        </div>
                    </template>

                    <div class="mt-7 grid gap-6 md:grid-cols-2">
                        <label class="field-group">
                            <span>{{ __('ui.admin.required_level') }}</span>
                            <input type="number" name="requiredLevel" min="1" max="400" x-model.number="settings.requiredLevel">
                            <small>{{ __('ui.admin.required_level_help') }}</small>
                            <small class="text-red-300" x-show="firstValidationError('requiredLevel')" x-text="firstValidationError('requiredLevel')"></small>
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.maximum_resets') }}</span>
                            <input type="number" name="maximumResets" min="1" max="10000" x-model.number="settings.maximumResets">
                            <small>{{ __('ui.admin.maximum_resets_help') }}</small>
                            <small class="text-red-300" x-show="firstValidationError('maximumResets')" x-text="firstValidationError('maximumResets')"></small>
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.maximum_stat') }}</span>
                            <input type="number" name="maximumStat" min="1" max="32767" x-model.number="settings.maximumStat">
                            <small>{{ __('ui.admin.maximum_stat_help') }}</small>
                            <small class="text-red-300" x-show="firstValidationError('maximumStat')" x-text="firstValidationError('maximumStat')"></small>
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.base_zen_cost') }}</span>
                            <input type="number" name="baseZenCost" min="0" max="2000000000" step="1000000" x-model.number="settings.baseZenCost">
                            <small>{{ __('ui.admin.base_zen_cost_help') }}</small>
                            <small class="text-red-300" x-show="firstValidationError('baseZenCost')" x-text="firstValidationError('baseZenCost')"></small>
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.points_per_reset') }}</span>
                            <input type="number" name="pointsPerReset" min="0" max="32767" x-model.number="settings.pointsPerReset">
                            <small>{{ __('ui.admin.points_per_reset_help') }}</small>
                            <small class="text-red-300" x-show="firstValidationError('pointsPerReset')" x-text="firstValidationError('pointsPerReset')"></small>
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.points_calculation') }}</span>
                            <select name="pointsMode" x-model="settings.pointsMode">
                                <option value="cumulative">{{ __('ui.admin.points_cumulative') }}</option>
                                <option value="fixed">{{ __('ui.admin.points_fixed') }}</option>
                            </select>
                            <small>{{ __('ui.admin.live_updates') }}</small>
                            <small class="text-red-300" x-show="firstValidationError('pointsMode')" x-text="firstValidationError('pointsMode')"></small>
                        </label>
                        <label class="field-group">
                            <span>{{ __('ui.admin.inventory_policy') }}</span>
                            <select name="inventoryPolicy" x-model="settings.inventoryPolicy">
                                <option value="keep_all">{{ __('ui.admin.keep_all') }}</option>
                                <option value="clear_bag">{{ __('ui.admin.clear_bag') }}</option>
                                <option value="clear_all">{{ __('ui.admin.clear_all') }}</option>
                            </select>
                            <small>{{ __('ui.admin.destructive_confirmation') }}</small>
                            <small class="text-red-300" x-show="firstValidationError('inventoryPolicy')" x-text="firstValidationError('inventoryPolicy')"></small>
                        </label>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2">
                        <label class="check-card">
                            <input type="checkbox" name="multiplyCostByReset" value="1" x-model="settings.multiplyCostByReset">
                            <span>
                                <strong>{{ __('ui.admin.scale_cost') }}</strong>
                                <small>{{ __('ui.admin.scale_cost_help') }}</small>
                            </span>
                        </label>
                        <label class="check-card">
                            <input type="checkbox" name="clearStats" value="1" x-model="settings.clearStats">
                            <span>
                                <strong>{{ __('ui.admin.clear_stats') }}</strong>
                                <small>{{ __('ui.admin.clear_stats_help') }}</small>
                            </span>
                        </label>
                    </div>

                    <div class="mt-8 border-t border-white/8 pt-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.admin.grand_reset_profile') }}</p>
                                <p class="mt-2 text-sm text-steel">{{ __('ui.admin.grand_reset_enabled_help') }}</p>
                            </div>
                            <label class="toggle-control">
                                <input type="checkbox" name="grandResetEnabled" value="1" x-model="settings.grandResetEnabled">
                                <span aria-hidden="true"></span>
                                <span>{{ __('ui.admin.grand_reset_enabled') }}</span>
                            </label>
                        </div>

                        <div class="mt-7 grid gap-6 md:grid-cols-2">
                            <label class="field-group">
                                <span>{{ __('ui.admin.grand_reset_required_level') }}</span>
                                <input type="number" name="grandResetRequiredLevel" min="1" max="400" x-model.number="settings.grandResetRequiredLevel">
                                <small>{{ __('ui.admin.grand_reset_required_level_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('grandResetRequiredLevel')" x-text="firstValidationError('grandResetRequiredLevel')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.grand_reset_required_resets') }}</span>
                                <input type="number" name="grandResetRequiredResets" min="1" :max="settings.maximumResets" x-model.number="settings.grandResetRequiredResets">
                                <small>{{ __('ui.admin.grand_reset_required_resets_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('grandResetRequiredResets')" x-text="firstValidationError('grandResetRequiredResets')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.maximum_grand_resets') }}</span>
                                <input type="number" name="maximumGrandResets" min="1" max="10000" x-model.number="settings.maximumGrandResets">
                                <small>{{ __('ui.admin.maximum_grand_resets_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('maximumGrandResets')" x-text="firstValidationError('maximumGrandResets')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.grand_reset_zen_cost') }}</span>
                                <input type="number" name="grandResetBaseZenCost" min="0" max="2000000000" step="1000000" x-model.number="settings.grandResetBaseZenCost">
                                <small>{{ __('ui.admin.grand_reset_zen_cost_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('grandResetBaseZenCost')" x-text="firstValidationError('grandResetBaseZenCost')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.grand_reset_points') }}</span>
                                <input type="number" name="grandResetPoints" min="0" max="2000000000" x-model.number="settings.grandResetPoints">
                                <small>{{ __('ui.admin.grand_reset_points_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('grandResetPoints')" x-text="firstValidationError('grandResetPoints')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.grand_reset_points_mode') }}</span>
                                <select name="grandResetPointsMode" x-model="settings.grandResetPointsMode">
                                    <option value="add_fixed">{{ __('ui.admin.grand_points_add_fixed') }}</option>
                                    <option value="add_cumulative">{{ __('ui.admin.grand_points_add_cumulative') }}</option>
                                    <option value="fixed_cumulative">{{ __('ui.admin.grand_points_fixed_cumulative') }}</option>
                                    <option value="fixed">{{ __('ui.admin.grand_points_fixed') }}</option>
                                </select>
                                <small class="text-red-300" x-show="firstValidationError('grandResetPointsMode')" x-text="firstValidationError('grandResetPointsMode')"></small>
                            </label>
                            <label class="field-group md:col-span-2">
                                <span>{{ __('ui.admin.grand_reset_inventory_policy') }}</span>
                                <select name="grandResetInventoryPolicy" x-model="settings.grandResetInventoryPolicy">
                                    <option value="keep_all">{{ __('ui.admin.keep_all') }}</option>
                                    <option value="clear_bag">{{ __('ui.admin.clear_bag') }}</option>
                                    <option value="clear_all">{{ __('ui.admin.clear_all') }}</option>
                                </select>
                                <small>{{ __('ui.admin.destructive_confirmation') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('grandResetInventoryPolicy')" x-text="firstValidationError('grandResetInventoryPolicy')"></small>
                            </label>
                        </div>

                        <div class="mt-7 grid gap-3 lg:grid-cols-3">
                            <label class="check-card">
                                <input type="checkbox" name="grandResetMultiplyCost" value="1" x-model="settings.grandResetMultiplyCost">
                                <span><strong>{{ __('ui.admin.grand_reset_scale_cost') }}</strong><small>{{ __('ui.admin.grand_reset_scale_cost_help') }}</small></span>
                            </label>
                            <label class="check-card">
                                <input type="checkbox" name="grandResetClearStats" value="1" x-model="settings.grandResetClearStats">
                                <span><strong>{{ __('ui.admin.grand_reset_clear_stats') }}</strong><small>{{ __('ui.admin.grand_reset_clear_stats_help') }}</small></span>
                            </label>
                            <label class="check-card">
                                <input type="checkbox" name="grandResetConsumeRequiredResets" value="1" x-model="settings.grandResetConsumeRequiredResets">
                                <span><strong>{{ __('ui.admin.grand_reset_consume_resets') }}</strong><small>{{ __('ui.admin.grand_reset_consume_resets_help') }}</small></span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-white/8 pt-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.admin.stat_reset_profile') }}</p>
                                <p class="mt-2 text-sm text-steel">{{ __('ui.admin.stat_reset_profile_help') }}</p>
                            </div>
                            <label class="toggle-control">
                                <input type="checkbox" name="statResetEnabled" value="1" x-model="settings.statResetEnabled">
                                <span aria-hidden="true"></span>
                                <span>{{ __('ui.admin.stat_reset_enabled') }}</span>
                            </label>
                        </div>

                        <div class="mt-7 grid gap-6 md:grid-cols-3">
                            <label class="field-group">
                                <span>{{ __('ui.admin.stat_reset_required_level') }}</span>
                                <input type="number" name="statResetRequiredLevel" min="1" max="400" x-model.number="settings.statResetRequiredLevel">
                                <small>{{ __('ui.admin.stat_reset_required_level_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('statResetRequiredLevel')" x-text="firstValidationError('statResetRequiredLevel')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.stat_reset_required_resets') }}</span>
                                <input type="number" name="statResetRequiredResets" min="0" :max="settings.maximumResets" x-model.number="settings.statResetRequiredResets">
                                <small>{{ __('ui.admin.stat_reset_required_resets_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('statResetRequiredResets')" x-text="firstValidationError('statResetRequiredResets')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.stat_reset_zen_cost') }}</span>
                                <input type="number" name="statResetZenCost" min="0" max="2000000000" step="1000000" x-model.number="settings.statResetZenCost">
                                <small>{{ __('ui.admin.stat_reset_zen_cost_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('statResetZenCost')" x-text="firstValidationError('statResetZenCost')"></small>
                            </label>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-white/8 pt-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.admin.pk_clear_profile') }}</p>
                                <p class="mt-2 text-sm text-steel">{{ __('ui.admin.pk_clear_profile_help') }}</p>
                            </div>
                            <label class="toggle-control">
                                <input type="checkbox" name="pkClearEnabled" value="1" x-model="settings.pkClearEnabled">
                                <span aria-hidden="true"></span>
                                <span>{{ __('ui.admin.pk_clear_enabled') }}</span>
                            </label>
                        </div>

                        <div class="mt-7 grid gap-6 md:grid-cols-3">
                            <label class="field-group">
                                <span>{{ __('ui.admin.pk_clear_required_level') }}</span>
                                <input type="number" name="pkClearRequiredLevel" min="1" max="400" x-model.number="settings.pkClearRequiredLevel">
                                <small>{{ __('ui.admin.pk_clear_required_level_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('pkClearRequiredLevel')" x-text="firstValidationError('pkClearRequiredLevel')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.pk_clear_base_zen_cost') }}</span>
                                <input type="number" name="pkClearBaseZenCost" min="0" max="2000000000" step="100000" x-model.number="settings.pkClearBaseZenCost">
                                <small>{{ __('ui.admin.pk_clear_base_zen_cost_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('pkClearBaseZenCost')" x-text="firstValidationError('pkClearBaseZenCost')"></small>
                            </label>
                            <label class="field-group">
                                <span>{{ __('ui.admin.pk_clear_price_mode') }}</span>
                                <select name="pkClearPriceMode" x-model="settings.pkClearPriceMode">
                                    <option value="fixed">{{ __('ui.admin.pk_clear_price_fixed') }}</option>
                                    <option value="by_pk_level">{{ __('ui.admin.pk_clear_price_by_level') }}</option>
                                    <option value="by_pk_count">{{ __('ui.admin.pk_clear_price_by_count') }}</option>
                                </select>
                                <small>{{ __('ui.admin.pk_clear_price_mode_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('pkClearPriceMode')" x-text="firstValidationError('pkClearPriceMode')"></small>
                            </label>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-white/8 pt-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.admin.move_to_town_profile') }}</p>
                                <p class="mt-2 text-sm text-steel">{{ __('ui.admin.move_to_town_profile_help') }}</p>
                            </div>
                            <label class="toggle-control">
                                <input type="checkbox" name="moveToTownEnabled" value="1" x-model="settings.moveToTownEnabled">
                                <span aria-hidden="true"></span>
                                <span>{{ __('ui.admin.move_to_town_enabled') }}</span>
                            </label>
                        </div>

                        <div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
                            @foreach (['moveToTownMap' => ['move_to_town_map', 0, 255], 'moveToTownX' => ['move_to_town_x', 0, 255], 'moveToTownY' => ['move_to_town_y', 0, 255]] as $field => [$label, $minimum, $maximum])
                                <label class="field-group">
                                    <span>{{ __('ui.admin.'.$label) }}</span>
                                    <input type="number" name="{{ $field }}" min="{{ $minimum }}" max="{{ $maximum }}" x-model.number="settings.{{ $field }}">
                                    <small class="text-red-300" x-show="firstValidationError('{{ $field }}')" x-text="firstValidationError('{{ $field }}')"></small>
                                </label>
                            @endforeach
                            <label class="field-group sm:col-span-2">
                                <span>{{ __('ui.admin.move_to_town_zen_cost') }}</span>
                                <input type="number" name="moveToTownZenCost" min="0" max="2000000000" step="1000" x-model.number="settings.moveToTownZenCost">
                                <small>{{ __('ui.admin.move_to_town_zen_cost_help') }}</small>
                                <small class="text-red-300" x-show="firstValidationError('moveToTownZenCost')" x-text="firstValidationError('moveToTownZenCost')"></small>
                            </label>
                        </div>
                    </div>
                </form>

                <aside class="grid content-start gap-4">
                    <section class="panel-card panel-card-accent">
                        <p class="kicker">{{ __('ui.admin.live_outcome') }}</p>
                        <h2 class="mt-2 text-xl font-semibold text-white">{{ __('ui.admin.reset_calculator') }}</h2>

                        <label class="field-group mt-6">
                            <span>{{ __('ui.admin.preview_reset_number') }}</span>
                            <input type="range" min="1" :max="settings.maximumResets" x-model.number="previewResetNumber">
                            <strong class="text-2xl text-gold" x-text="previewResetNumber"></strong>
                        </label>

                        <dl class="mt-6 grid gap-3">
                            <div class="preview-row"><dt>{{ __('ui.admin.required_level') }}</dt><dd x-text="settings.requiredLevel"></dd></div>
                            <div class="preview-row"><dt>{{ __('ui.admin.zen_cost') }}</dt><dd x-text="previewZenCost.toLocaleString()"></dd></div>
                            <div class="preview-row"><dt>{{ __('ui.admin.level_up_points') }}</dt><dd x-text="previewPoints.toLocaleString()"></dd></div>
                            <div class="preview-row"><dt>{{ __('ui.admin.maximum_stat') }}</dt><dd x-text="Number(settings.maximumStat).toLocaleString()"></dd></div>
                            <div class="preview-row"><dt>{{ __('ui.admin.grand_reset_requirement') }}</dt><dd x-text="Number(settings.grandResetRequiredResets).toLocaleString()"></dd></div>
                            <div class="preview-row"><dt>{{ __('ui.admin.stats') }}</dt><dd x-text="statsPolicyLabel"></dd></div>
                            <div class="preview-row"><dt>{{ __('ui.admin.items') }}</dt><dd x-text="inventoryPolicyLabel"></dd></div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-emerald-300/15 bg-emerald-300/6 p-4 text-sm leading-6 text-emerald-100">
                        <strong class="block text-emerald-200">{{ __('ui.admin.safe_draft') }}</strong>
                        {{ __('ui.admin.safe_draft_help') }}
                    </section>

                    <section class="panel-card p-4">
                        <p class="kicker">{{ __('ui.admin.revision_history') }}</p>
                        <div class="mt-4 grid gap-2">
                            <template x-for="revisionEntry in revisions" :key="revisionEntry.revision">
                                <div class="rounded-xl border border-white/8 bg-black/20 px-3 py-2">
                                    <p
                                        class="text-sm font-semibold text-white"
                                        x-text="`${translations.revision} ${revisionEntry.revision}`"
                                    ></p>
                                    <p class="mt-1 text-xs text-steel" x-text="revisionEntry.changedAt"></p>
                                </div>
                            </template>
                            <template x-if="revisions.length === 0">
                                <p class="text-sm text-steel">{{ __('ui.admin.no_revisions') }}</p>
                            </template>
                        </div>
                    </section>
                </aside>
            </div>
        </div>

        <div
            class="fixed bottom-6 right-6 z-50 rounded-2xl border px-5 py-4 text-sm shadow-2xl"
            :class="savedNoticeIsError
                ? 'border-red-300/20 bg-[#241414] text-red-100'
                : 'border-emerald-300/20 bg-[#101b17] text-emerald-100'"
            x-show="savedNoticeVisible"
            x-transition
            x-cloak
        >
            <span x-text="savedNoticeMessage">{{ __('ui.admin.draft_saved') }}</span>
        </div>
    </div>
@endsection
