@extends('layouts.app')

@section('title', __('ui.account.title'))

@section('content')
    <div
        class="shell py-10 lg:py-14"
        x-data="accountPanel(
            {{ Js::from($characters) }},
            {{ Js::from([
                'previewUrl' => route('account.reset-preview'),
                'executeUrl' => route('account.character-reset'),
                'grandResetPreviewUrl' => route('account.grand-reset-preview'),
                'grandResetExecuteUrl' => route('account.character-grand-reset'),
                'statResetPreviewUrl' => route('account.stat-reset-preview'),
                'statResetExecuteUrl' => route('account.character-stat-reset'),
                'pkClearPreviewUrl' => route('account.pk-clear-preview'),
                'pkClearExecuteUrl' => route('account.character-pk-clear'),
                'statPreviewUrl' => route('account.stat-allocation-preview'),
                'statExecuteUrl' => route('account.stat-allocations.store'),
                'writesEnabled' => $gameWritesEnabled,
                'csrfToken' => csrf_token(),
                'previewFailed' => __('ui.account.reset_preview_failed'),
                'executionFailed' => __('ui.account.reset_operation_failed'),
                'completed' => __('ui.account.reset_completed'),
                'grandResetPreviewFailed' => __('ui.account.grand_reset_preview_failed'),
                'grandResetExecutionFailed' => __('ui.account.grand_reset_operation_failed'),
                'grandResetCompleted' => __('ui.account.grand_reset_completed'),
                'statResetPreviewFailed' => __('ui.account.stat_reset_preview_failed'),
                'statResetExecutionFailed' => __('ui.account.stat_reset_operation_failed'),
                'statResetCompleted' => __('ui.account.stat_reset_completed'),
                'pkClearPreviewFailed' => __('ui.account.pk_clear_preview_failed'),
                'pkClearExecutionFailed' => __('ui.account.pk_clear_operation_failed'),
                'pkClearCompleted' => __('ui.account.pk_clear_completed'),
                'statPreviewFailed' => __('ui.account.allocation_preview_failed'),
                'statExecutionFailed' => __('ui.account.stat_allocation_failed'),
                'statCompleted' => __('ui.account.stat_allocation_completed'),
                'inventoryPolicies' => [
                    'keep_all' => __('ui.account.inventory_keep_all'),
                    'clear_bag' => __('ui.account.inventory_clear_bag'),
                    'clear_all' => __('ui.account.inventory_clear_all'),
                ],
                'blockReasons' => [
                    'profile_disabled' => __('ui.account.reset_profile_disabled'),
                    'character_online' => __('ui.account.reset_character_online'),
                    'level_too_low' => __('ui.account.reset_level_too_low'),
                    'maximum_resets_reached' => __('ui.account.reset_maximum_reached'),
                    'insufficient_zen' => __('ui.account.reset_insufficient_zen'),
                ],
                'executionReasons' => [
                    'writes_disabled' => __('ui.account.reset_writes_disabled'),
                    'inventory_policy_unsupported' => __('ui.account.reset_inventory_policy_unsupported'),
                    'operation_in_progress' => __('ui.account.reset_operation_in_progress'),
                    'idempotency_conflict' => __('ui.account.reset_idempotency_conflict'),
                    'character_class_unsupported' => __('ui.account.reset_character_class_unsupported'),
                    'character_online' => __('ui.account.reset_character_online'),
                    'level_too_low' => __('ui.account.reset_level_too_low'),
                    'maximum_resets_reached' => __('ui.account.reset_maximum_reached'),
                    'insufficient_zen' => __('ui.account.reset_insufficient_zen'),
                ],
                'grandResetBlockReasons' => [
                    'profile_disabled' => __('ui.account.grand_reset_profile_disabled'),
                    'character_online' => __('ui.account.reset_character_online'),
                    'level_too_low' => __('ui.account.grand_reset_level_too_low'),
                    'insufficient_resets' => __('ui.account.grand_reset_insufficient_resets'),
                    'maximum_grand_resets_reached' => __('ui.account.grand_reset_maximum_reached'),
                    'insufficient_zen' => __('ui.account.grand_reset_insufficient_zen'),
                    'point_limit_exceeded' => __('ui.account.grand_reset_point_limit'),
                    'inventory_policy_unsupported' => __('ui.account.reset_inventory_policy_unsupported'),
                    'character_class_unsupported' => __('ui.account.reset_character_class_unsupported'),
                    'writes_disabled' => __('ui.account.reset_writes_disabled'),
                    'operation_in_progress' => __('ui.account.grand_reset_operation_in_progress'),
                    'idempotency_conflict' => __('ui.account.grand_reset_idempotency_conflict'),
                ],
                'statResetBlockReasons' => [
                    'profile_disabled' => __('ui.account.stat_reset_profile_disabled'),
                    'character_online' => __('ui.account.reset_character_online'),
                    'level_too_low' => __('ui.account.stat_reset_level_too_low'),
                    'insufficient_resets' => __('ui.account.stat_reset_insufficient_resets'),
                    'insufficient_zen' => __('ui.account.stat_reset_insufficient_zen'),
                    'no_allocated_stats' => __('ui.account.stat_reset_no_allocated_stats'),
                    'character_stats_invalid' => __('ui.account.stat_reset_character_stats_invalid'),
                    'point_limit_exceeded' => __('ui.account.stat_reset_point_limit'),
                    'character_class_unsupported' => __('ui.account.reset_character_class_unsupported'),
                    'writes_disabled' => __('ui.account.reset_writes_disabled'),
                    'operation_in_progress' => __('ui.account.stat_reset_operation_in_progress'),
                    'idempotency_conflict' => __('ui.account.stat_reset_idempotency_conflict'),
                ],
                'pkClearBlockReasons' => [
                    'profile_disabled' => __('ui.account.pk_clear_profile_disabled'),
                    'character_online' => __('ui.account.reset_character_online'),
                    'level_too_low' => __('ui.account.pk_clear_level_too_low'),
                    'character_not_killer' => __('ui.account.pk_clear_not_killer'),
                    'price_limit_exceeded' => __('ui.account.pk_clear_price_limit'),
                    'insufficient_zen' => __('ui.account.pk_clear_insufficient_zen'),
                    'writes_disabled' => __('ui.account.reset_writes_disabled'),
                    'operation_in_progress' => __('ui.account.pk_clear_operation_in_progress'),
                    'idempotency_conflict' => __('ui.account.pk_clear_idempotency_conflict'),
                ],
                'pkClearPriceModes' => [
                    'fixed' => __('ui.account.pk_clear_price_fixed'),
                    'by_pk_level' => __('ui.account.pk_clear_price_by_level'),
                    'by_pk_count' => __('ui.account.pk_clear_price_by_count'),
                ],
                'statBlockReasons' => [
                    'character_online' => __('ui.account.reset_character_online'),
                    'no_points_allocated' => __('ui.account.allocation_no_points'),
                    'invalid_allocation' => __('ui.account.allocation_invalid'),
                    'insufficient_points' => __('ui.account.allocation_insufficient_points'),
                    'stat_limit_exceeded' => __('ui.account.allocation_stat_limit'),
                    'character_class_unsupported' => __('ui.account.allocation_class_unsupported'),
                    'writes_disabled' => __('ui.account.reset_writes_disabled'),
                    'operation_in_progress' => __('ui.account.reset_operation_in_progress'),
                    'idempotency_conflict' => __('ui.account.reset_idempotency_conflict'),
                ],
            ]) }}
        )"
    >
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="eyebrow mb-4">{{ __('ui.account.eyebrow') }}</div>
                <h1 class="font-display text-4xl text-white sm:text-5xl">{{ __('ui.account.heading') }}</h1>
                <p class="mt-3 max-w-2xl text-silver">{{ __('ui.account.intro') }}</p>
            </div>
            <div class="flex gap-3">
                <div class="resource-chip">
                    <span>{{ __('ui.account.signed_in_as') }}</span>
                    <strong>{{ $account->username }}</strong>
                </div>
                <div class="resource-chip">
                    <span>{{ __('ui.account.vip') }}</span>
                    <strong class="text-gold">{{ $account->vip ? __('ui.account.vip_active') : __('ui.account.vip_inactive') }}</strong>
                </div>
            </div>
        </div>

        @if ($characters === [])
            <article class="panel-card mt-8 text-center">
                <p class="kicker">{{ __('ui.account.no_characters') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('ui.account.no_characters_heading') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.account.no_characters_help') }}</p>
            </article>
        @else
        <div class="mt-8 flex gap-3 overflow-x-auto pb-2">
            <template x-for="(character, characterIndex) in characters" :key="character.name">
                <button
                    type="button"
                    class="character-selector"
                    :class="selectedCharacterIndex === characterIndex && 'character-selector-active'"
                    @click="selectCharacter(characterIndex)"
                >
                    <img :src="character.image" :alt="character.class" class="size-11 rounded-xl object-cover object-top">
                    <span class="text-left">
                        <strong class="block text-sm text-white" x-text="character.name"></strong>
                        <span class="block text-xs text-steel" x-text="character.class"></span>
                    </span>
                    <span class="ml-3 status-dot status-dot-small" :class="!character.online && 'status-dot-offline'"></span>
                </button>
            </template>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[22rem_1fr]">
            <aside class="character-portrait-card">
                <div class="absolute inset-x-10 top-14 h-48 rounded-full bg-crimson/20 blur-3xl" aria-hidden="true"></div>
                <img
                    :src="selectedCharacter.image"
                    :alt="selectedCharacter.class"
                    class="relative z-10 h-[27rem] w-full object-cover object-top"
                >
                <div class="relative z-20 -mt-28 bg-gradient-to-t from-[#0b0d12] via-[#0b0d12]/95 to-transparent px-6 pb-6 pt-28">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="kicker" x-text="selectedCharacter.class"></p>
                            <h2 class="mt-1 font-display text-3xl text-white" x-text="selectedCharacter.name"></h2>
                        </div>
                        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-silver" x-text="selectedCharacter.map"></span>
                    </div>
                    <div class="mt-5 grid grid-cols-3 gap-2">
                        <div class="portrait-metric"><strong x-text="selectedCharacter.level"></strong><span>{{ __('ui.account.level') }}</span></div>
                        <div class="portrait-metric"><strong x-text="selectedCharacter.resets"></strong><span>{{ __('ui.account.resets') }}</span></div>
                        <div class="portrait-metric"><strong x-text="selectedCharacter.grandResets"></strong><span>{{ __('ui.account.grand') }}</span></div>
                    </div>
                </div>
            </aside>

            <section class="min-w-0">
                <div class="segmented-control w-full overflow-x-auto sm:w-fit" role="tablist" aria-label="{{ __('ui.account.character_tools') }}">
                    @foreach (['overview' => __('ui.account.overview'), 'inventory' => __('ui.account.inventory'), 'stats' => __('ui.account.allocate_stats'), 'stat-reset' => __('ui.account.stat_reset'), 'pk-clear' => __('ui.account.pk_clear'), 'reset' => __('ui.account.reset'), 'grand-reset' => __('ui.account.grand_reset')] as $section => $label)
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeSection === '{{ $section }}'"
                            :class="activeSection === '{{ $section }}' && 'segmented-control-active'"
                            @click="selectSection('{{ $section }}')"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <div class="mt-5" x-show="activeSection === 'overview'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <article class="panel-card">
                            <p class="kicker">{{ __('ui.account.core_attributes') }}</p>
                            <div class="mt-6 grid gap-4">
                                @foreach (['strength' => __('ui.account.strength'), 'agility' => __('ui.account.agility'), 'vitality' => __('ui.account.vitality'), 'energy' => __('ui.account.energy')] as $stat => $label)
                                    <div class="stat-line">
                                        <span>{{ $label }}</span>
                                        <strong x-text="selectedCharacter.{{ $stat }}.toLocaleString()"></strong>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                        <article class="panel-card">
                            <p class="kicker">{{ __('ui.account.quick_actions') }}</p>
                            <div class="mt-6 grid gap-3">
                                <button type="button" class="action-row" @click="selectSection('reset')">
                                    <span><strong>{{ __('ui.account.reset_character') }}</strong><small>{{ __('ui.account.preview_cost') }}</small></span>
                                    <span aria-hidden="true">→</span>
                                </button>
                                <button type="button" class="action-row" @click="selectSection('stats')">
                                    <span><strong>{{ __('ui.account.allocate_stats') }}</strong><small><span x-text="selectedCharacter.points.toLocaleString()"></span> {{ __('ui.account.points_available') }}</small></span>
                                    <span aria-hidden="true">→</span>
                                </button>
                                <button type="button" class="action-row" @click="selectSection('grand-reset')">
                                    <span><strong>{{ __('ui.account.grand_reset_character') }}</strong><small>{{ __('ui.account.preview_cost') }}</small></span>
                                    <span aria-hidden="true">→</span>
                                </button>
                                <button type="button" class="action-row" @click="selectSection('stat-reset')">
                                    <span><strong>{{ __('ui.account.stat_reset_character') }}</strong><small>{{ __('ui.account.stat_reset_refund_help') }}</small></span>
                                    <span aria-hidden="true">→</span>
                                </button>
                                <button type="button" class="action-row" @click="selectSection('pk-clear')">
                                    <span><strong>{{ __('ui.account.pk_clear_character') }}</strong><small>{{ __('ui.account.pk_clear_help') }}</small></span>
                                    <span aria-hidden="true">→</span>
                                </button>
                                <button type="button" class="action-row">
                                    <span><strong>{{ __('ui.account.move_to_town') }}</strong><small>{{ __('ui.account.offline_only') }}</small></span>
                                    <span aria-hidden="true">→</span>
                                </button>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="mt-5" x-show="activeSection === 'inventory'" x-cloak>
                    <article class="panel-card">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.account.decoded_grid') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.inventory') }}</h3>
                            </div>
                            <p class="text-sm text-steel">{{ __('ui.account.item_details') }}</p>
                        </div>
                        <div class="inventory-grid mt-6">
                            @for ($slot = 0; $slot < 64; $slot++)
                                @php($item = collect($inventoryItems)->firstWhere('slot', $slot))
                                <div class="inventory-slot">
                                    @if ($item)
                                        <button type="button" class="inventory-item inventory-item-{{ $item['rarity'] }}" aria-label="{{ $item['name'] }}">
                                            <span>{{ $item['code'] }}</span>
                                            <span class="item-tooltip">{{ $item['name'] }}</span>
                                        </button>
                                    @endif
                                </div>
                            @endfor
                        </div>
                    </article>
                </div>

                <div class="mt-5" x-show="activeSection === 'stats'" x-cloak>
                    <article class="panel-card">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="kicker">{{ __('ui.account.safe_preview') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.allocate_stats') }}</h3>
                            </div>
                            <div class="text-left sm:text-right">
                                <p class="text-sm text-steel">{{ __('ui.account.remaining_points') }}</p>
                                <p class="text-3xl font-semibold text-gold" x-text="remainingPoints.toLocaleString()"></p>
                            </div>
                        </div>
                        <div class="mt-7 grid gap-3">
                            @foreach (['strength' => __('ui.account.strength'), 'agility' => __('ui.account.agility'), 'vitality' => __('ui.account.vitality'), 'energy' => __('ui.account.energy')] as $stat => $label)
                                <div class="allocation-row">
                                    <div>
                                        <strong>{{ $label }}</strong>
                                        <span>
                                            <span x-text="selectedCharacter.{{ $stat }}.toLocaleString()"></span>
                                            <span class="text-gold" x-show="allocations.{{ $stat }} > 0">+ <span x-text="allocations.{{ $stat }}"></span></span>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="step-button" @click="removePoints('{{ $stat }}', 10)">−10</button>
                                        <button type="button" class="step-button" @click="allocatePoints('{{ $stat }}', 10)">+10</button>
                                        <button type="button" class="step-button" @click="allocatePoints('{{ $stat }}', 100)">+100</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div
                            class="mt-6 rounded-2xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100"
                            x-show="statPreviewError"
                            x-text="statPreviewError"
                            role="alert"
                        ></div>

                        <div
                            class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 p-4 text-sm text-emerald-100"
                            x-show="statSuccessMessage"
                            x-text="statSuccessMessage"
                            role="status"
                        ></div>

                        <template x-if="statPreview !== null && !statPreviewLoading">
                            <div class="mt-6 rounded-2xl border border-white/10 bg-white/4 p-5">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <p class="kicker">{{ __('ui.account.allocation_preview') }}</p>
                                        <p class="mt-2 text-sm text-silver">{{ __('ui.account.allocation_preview_help') }}</p>
                                    </div>
                                    <strong class="text-gold"><span x-text="statPreview.allocatedPoints.toLocaleString()"></span> {{ __('ui.account.allocated_points') }}</strong>
                                </div>

                                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach (['strength' => __('ui.account.strength'), 'agility' => __('ui.account.agility'), 'vitality' => __('ui.account.vitality'), 'energy' => __('ui.account.energy')] as $stat => $label)
                                        <div class="outcome-card">
                                            <span>{{ $label }}</span>
                                            <strong>
                                                <span x-text="statPreview.currentStats.{{ $stat }}.toLocaleString()"></span>
                                                →
                                                <span x-text="statPreview.resultingStats.{{ $stat }}.toLocaleString()"></span>
                                            </strong>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div class="outcome-card"><span>{{ __('ui.account.points_before') }}</span><strong x-text="statPreview.currentPoints.toLocaleString()"></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.allocated_points') }}</span><strong x-text="statPreview.allocatedPoints.toLocaleString()"></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.points_after') }}</span><strong x-text="statPreview.remainingPoints.toLocaleString()"></strong></div>
                                </div>

                                <div
                                    class="mt-5 rounded-2xl border p-4 text-sm leading-6"
                                    :class="statPreview.eligible
                                        ? 'border-emerald-300/20 bg-emerald-300/7 text-emerald-100'
                                        : 'border-amber-300/20 bg-amber-300/7 text-amber-100'"
                                >
                                    <strong
                                        class="block"
                                        x-text="statPreview.eligible
                                            ? @js(__('ui.account.allocation_requirements_met'))
                                            : @js(__('ui.account.allocation_requirements_failed'))"
                                    ></strong>
                                    <ul class="mt-2 grid gap-1" x-show="!statPreview.eligible">
                                        <template x-for="blockMessage in statBlockMessages" :key="blockMessage">
                                            <li>• <span x-text="blockMessage"></span></li>
                                        </template>
                                    </ul>
                                </div>

                                <div
                                    class="mt-5 rounded-2xl border border-gold/20 bg-gold/5 p-5"
                                    x-show="statConfirmationOpen"
                                    x-cloak
                                >
                                    <strong class="block text-white">{{ __('ui.account.confirm_allocation') }}</strong>
                                    <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.confirm_allocation_help') }}</p>
                                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                                        <button type="button" class="button button-ghost" :disabled="statExecuting" @click="closeStatConfirmation">{{ __('ui.account.reset_cancel') }}</button>
                                        <button type="button" class="button button-primary" :disabled="statExecuting" @click="executeStatAllocation">
                                            <span x-show="!statExecuting">{{ __('ui.account.apply_allocation') }}</span>
                                            <span x-show="statExecuting">{{ __('ui.account.applying_allocation') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div
                                    class="mt-5 rounded-2xl border border-white/10 bg-white/4 p-4 text-sm leading-6 text-silver"
                                    x-show="!resetPreviewConfiguration.writesEnabled"
                                >{{ __('ui.account.reset_writes_disabled') }}</div>

                                <div class="mt-5 flex justify-end" x-show="!statConfirmationOpen">
                                    <button
                                        type="button"
                                        class="button button-primary"
                                        :disabled="!statPreview.eligible || !resetPreviewConfiguration.writesEnabled"
                                        @click="openStatConfirmation"
                                    >{{ __('ui.account.confirm_allocation') }}</button>
                                </div>
                            </div>
                        </template>

                        <div class="mt-7 flex flex-wrap justify-end gap-3">
                            <button type="button" class="button button-ghost" @click="resetAllocations">{{ __('ui.account.clear_preview') }}</button>
                            <button
                                type="button"
                                class="button button-primary"
                                :disabled="allocatedPoints === 0 || statPreviewLoading"
                                @click="reviewStatAllocation"
                            >
                                <span x-show="!statPreviewLoading">{{ __('ui.account.review_allocation') }}</span>
                                <span x-show="statPreviewLoading">{{ __('ui.account.applying_allocation') }}</span>
                            </button>
                        </div>
                    </article>
                </div>

                <div class="mt-5" x-show="activeSection === 'stat-reset'" x-cloak>
                    <article class="panel-card">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="kicker">{{ __('ui.account.transactional_operation') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.stat_reset_preview') }}</h3>
                                <p class="mt-2 text-sm text-steel">{{ __('ui.account.stat_reset_refund_help') }}</p>
                            </div>
                            <span class="preview-badge">{{ __('ui.account.preview_only') }}</span>
                        </div>

                        <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" x-show="statResetPreviewLoading">
                            @for ($placeholder = 0; $placeholder < 4; $placeholder++)
                                <div class="outcome-card animate-pulse" aria-hidden="true">
                                    <span class="h-3 w-20 rounded bg-white/8"></span>
                                    <strong class="mt-3 h-6 w-28 rounded bg-white/10"></strong>
                                </div>
                            @endfor
                        </div>

                        <div
                            class="mt-7 rounded-2xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100"
                            x-show="statResetPreviewError"
                            x-text="statResetPreviewError"
                            role="alert"
                        ></div>

                        <div
                            class="mt-7 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 p-4 text-sm text-emerald-100"
                            x-show="statResetSuccessMessage"
                            x-text="statResetSuccessMessage"
                            role="status"
                        ></div>

                        <template x-if="statResetPreview !== null && !statResetPreviewLoading">
                            <div>
                                <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach (['strength' => __('ui.account.strength'), 'agility' => __('ui.account.agility'), 'vitality' => __('ui.account.vitality'), 'energy' => __('ui.account.energy')] as $stat => $label)
                                        <div class="outcome-card">
                                            <span>{{ $label }}</span>
                                            <strong>
                                                <span x-text="statResetPreview.currentStats.{{ $stat }}.toLocaleString()"></span>
                                                →
                                                <span x-text="statResetPreview.resultingStats.{{ $stat }}.toLocaleString()"></span>
                                            </strong>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="outcome-card"><span>{{ __('ui.account.points_before') }}</span><strong x-text="statResetPreview.currentPoints.toLocaleString()"></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.stat_reset_refunded_points') }}</span><strong class="text-gold">+<span x-text="statResetPreview.refundedPoints.toLocaleString()"></span></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.points_after') }}</span><strong x-text="statResetPreview.resultingPoints.toLocaleString()"></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.cost') }}</span><strong><span x-text="statResetPreview.zenCost.toLocaleString()"></span> Zen</strong></div>
                                </div>

                                <div
                                    class="mt-6 rounded-2xl border p-4 text-sm leading-6"
                                    :class="statResetPreview.eligible
                                        ? 'border-emerald-300/20 bg-emerald-300/7 text-emerald-100'
                                        : 'border-amber-300/20 bg-amber-300/7 text-amber-100'"
                                >
                                    <strong
                                        class="block"
                                        x-text="statResetPreview.eligible
                                            ? @js(__('ui.account.stat_reset_requirements_met'))
                                            : @js(__('ui.account.stat_reset_requirements_failed'))"
                                    ></strong>
                                    <ul class="mt-2 grid gap-1" x-show="!statResetPreview.eligible">
                                        <template x-for="blockMessage in statResetBlockMessages" :key="blockMessage">
                                            <li>• <span x-text="blockMessage"></span></li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="mt-6 rounded-2xl border border-white/10 bg-white/4 p-4 text-sm leading-6 text-silver">
                                    <span x-show="resetPreviewConfiguration.writesEnabled">{{ __('ui.account.stat_reset_preview_notice') }}</span>
                                    <span x-show="!resetPreviewConfiguration.writesEnabled">{{ __('ui.account.reset_writes_disabled') }}</span>
                                </div>

                                <div
                                    class="mt-6 rounded-2xl border border-gold/20 bg-gold/5 p-5"
                                    x-show="statResetConfirmationOpen"
                                    x-cloak
                                >
                                    <strong class="block text-white">{{ __('ui.account.stat_reset_confirm') }}</strong>
                                    <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.stat_reset_confirm_help') }}</p>
                                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                                        <button
                                            type="button"
                                            class="button button-ghost"
                                            :disabled="statResetExecuting"
                                            @click="closeStatResetConfirmation"
                                        >{{ __('ui.account.reset_cancel') }}</button>
                                        <button
                                            type="button"
                                            class="button button-primary"
                                            :disabled="statResetExecuting"
                                            @click="executeStatReset"
                                        >
                                            <span x-show="!statResetExecuting">{{ __('ui.account.stat_reset_now') }}</span>
                                            <span x-show="statResetExecuting">{{ __('ui.account.stat_resetting') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-7 flex justify-end" x-show="!statResetConfirmationOpen">
                                    <button
                                        type="button"
                                        class="button button-primary"
                                        :disabled="!statResetPreview.eligible || !resetPreviewConfiguration.writesEnabled || statResetExecuting"
                                        @click="openStatResetConfirmation"
                                    >
                                        {{ __('ui.account.stat_reset_confirm') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </article>
                </div>

                <div class="mt-5" x-show="activeSection === 'pk-clear'" x-cloak>
                    <article class="panel-card">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="kicker">{{ __('ui.account.transactional_operation') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.pk_clear_preview') }}</h3>
                                <p class="mt-2 text-sm text-steel">{{ __('ui.account.pk_clear_help') }}</p>
                            </div>
                            <span class="preview-badge">{{ __('ui.account.preview_only') }}</span>
                        </div>

                        <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" x-show="pkClearPreviewLoading">
                            @for ($placeholder = 0; $placeholder < 4; $placeholder++)
                                <div class="outcome-card animate-pulse" aria-hidden="true">
                                    <span class="h-3 w-20 rounded bg-white/8"></span>
                                    <strong class="mt-3 h-6 w-28 rounded bg-white/10"></strong>
                                </div>
                            @endfor
                        </div>

                        <div class="mt-7 rounded-2xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100" x-show="pkClearPreviewError" x-text="pkClearPreviewError" role="alert"></div>
                        <div class="mt-7 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 p-4 text-sm text-emerald-100" x-show="pkClearSuccessMessage" x-text="pkClearSuccessMessage" role="status"></div>

                        <template x-if="pkClearPreview !== null && !pkClearPreviewLoading">
                            <div>
                                <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="outcome-card"><span>{{ __('ui.account.pk_level') }}</span><strong><span x-text="pkClearPreview.currentPkLevel"></span> → <span x-text="pkClearPreview.resultingPkLevel"></span></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.pk_count') }}</span><strong><span x-text="pkClearPreview.currentPkCount"></span> → <span x-text="pkClearPreview.resultingPkCount"></span></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.pk_time') }}</span><strong><span x-text="pkClearPreview.currentPkTime.toLocaleString()"></span> → <span x-text="pkClearPreview.resultingPkTime"></span></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.cost') }}</span><strong><span x-text="pkClearPreview.zenCost.toLocaleString()"></span> Zen</strong></div>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="outcome-card"><span>{{ __('ui.account.zen_after_pk_clear') }}</span><strong x-text="pkClearPreview.resultingZen.toLocaleString()"></strong></div>
                                    <div class="outcome-card"><span>{{ __('ui.account.pk_clear_price_formula') }}</span><strong x-text="pkClearPriceModeLabel"></strong></div>
                                </div>

                                <div class="mt-6 rounded-2xl border p-4 text-sm leading-6" :class="pkClearPreview.eligible ? 'border-emerald-300/20 bg-emerald-300/7 text-emerald-100' : 'border-amber-300/20 bg-amber-300/7 text-amber-100'">
                                    <strong class="block" x-text="pkClearPreview.eligible ? @js(__('ui.account.pk_clear_requirements_met')) : @js(__('ui.account.pk_clear_requirements_failed'))"></strong>
                                    <ul class="mt-2 grid gap-1" x-show="!pkClearPreview.eligible">
                                        <template x-for="blockMessage in pkClearBlockMessages" :key="blockMessage">
                                            <li>• <span x-text="blockMessage"></span></li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="mt-6 rounded-2xl border border-white/10 bg-white/4 p-4 text-sm leading-6 text-silver">
                                    <span x-show="resetPreviewConfiguration.writesEnabled">{{ __('ui.account.pk_clear_preview_notice') }}</span>
                                    <span x-show="!resetPreviewConfiguration.writesEnabled">{{ __('ui.account.reset_writes_disabled') }}</span>
                                </div>

                                <div class="mt-6 rounded-2xl border border-gold/20 bg-gold/5 p-5" x-show="pkClearConfirmationOpen" x-cloak>
                                    <strong class="block text-white">{{ __('ui.account.pk_clear_confirm') }}</strong>
                                    <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.pk_clear_confirm_help') }}</p>
                                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                                        <button type="button" class="button button-ghost" :disabled="pkClearExecuting" @click="closePkClearConfirmation">{{ __('ui.account.reset_cancel') }}</button>
                                        <button type="button" class="button button-primary" :disabled="pkClearExecuting" @click="executePkClear">
                                            <span x-show="!pkClearExecuting">{{ __('ui.account.pk_clear_now') }}</span>
                                            <span x-show="pkClearExecuting">{{ __('ui.account.pk_clearing') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-7 flex justify-end" x-show="!pkClearConfirmationOpen">
                                    <button type="button" class="button button-primary" :disabled="!pkClearPreview.eligible || !resetPreviewConfiguration.writesEnabled || pkClearExecuting" @click="openPkClearConfirmation">{{ __('ui.account.pk_clear_confirm') }}</button>
                                </div>
                            </div>
                        </template>
                    </article>
                </div>

                <div class="mt-5" x-show="activeSection === 'reset'" x-cloak>
                    <article class="panel-card">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="kicker">{{ __('ui.account.transactional_operation') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.reset_preview') }}</h3>
                            </div>
                            <span class="preview-badge">{{ __('ui.account.preview_only') }}</span>
                        </div>

                        <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" x-show="resetPreviewLoading">
                            @for ($placeholder = 0; $placeholder < 4; $placeholder++)
                                <div class="outcome-card animate-pulse" aria-hidden="true">
                                    <span class="h-3 w-20 rounded bg-white/8"></span>
                                    <strong class="mt-3 h-6 w-28 rounded bg-white/10"></strong>
                                </div>
                            @endfor
                        </div>

                        <div
                            class="mt-7 rounded-2xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100"
                            x-show="resetPreviewError"
                            x-text="resetPreviewError"
                            role="alert"
                        ></div>

                        <div
                            class="mt-7 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 p-4 text-sm text-emerald-100"
                            x-show="resetSuccessMessage"
                            x-text="resetSuccessMessage"
                            role="status"
                        ></div>

                        <template x-if="resetPreview !== null && !resetPreviewLoading">
                            <div>
                                <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.level') }}</span>
                                        <strong><span x-text="resetPreview.currentLevel"></span> → <span x-text="resetPreview.resultingLevel"></span></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.resets') }}</span>
                                        <strong><span x-text="resetPreview.currentResets"></span> → <span x-text="resetPreview.resultingResets"></span></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.inventory') }}</span>
                                        <strong x-text="resetInventoryPolicyLabel"></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.cost') }}</span>
                                        <strong><span x-text="resetPreview.zenCost.toLocaleString()"></span> Zen</strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.zen_after_reset') }}</span>
                                        <strong><span x-text="resetPreview.resultingZen.toLocaleString()"></span> Zen</strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.reset_points') }}</span>
                                        <strong x-text="resetPreview.resultingPoints.toLocaleString()"></strong>
                                    </div>
                                </div>

                                <div
                                    class="mt-6 rounded-2xl border p-4 text-sm leading-6"
                                    :class="resetPreview.eligible
                                        ? 'border-emerald-300/20 bg-emerald-300/7 text-emerald-100'
                                        : 'border-amber-300/20 bg-amber-300/7 text-amber-100'"
                                >
                                    <strong
                                        class="block"
                                        x-text="resetPreview.eligible
                                            ? @js(__('ui.account.reset_requirements_met'))
                                            : @js(__('ui.account.reset_requirements_failed'))"
                                    ></strong>
                                    <ul class="mt-2 grid gap-1" x-show="!resetPreview.eligible">
                                        <template x-for="blockMessage in resetBlockMessages" :key="blockMessage">
                                            <li>• <span x-text="blockMessage"></span></li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="mt-6 rounded-2xl border border-white/10 bg-white/4 p-4 text-sm leading-6 text-silver">
                                    <span x-show="resetPreviewConfiguration.writesEnabled">{{ __('ui.account.reset_preview_notice') }}</span>
                                    <span x-show="!resetPreviewConfiguration.writesEnabled">{{ __('ui.account.reset_writes_disabled') }}</span>
                                </div>

                                <div
                                    class="mt-6 rounded-2xl border border-gold/20 bg-gold/5 p-5"
                                    x-show="resetConfirmationOpen"
                                    x-cloak
                                >
                                    <strong class="block text-white">{{ __('ui.account.reset_confirm') }}</strong>
                                    <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.reset_confirm_help') }}</p>
                                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                                        <button
                                            type="button"
                                            class="button button-ghost"
                                            :disabled="resetExecuting"
                                            @click="closeResetConfirmation"
                                        >{{ __('ui.account.reset_cancel') }}</button>
                                        <button
                                            type="button"
                                            class="button button-primary"
                                            :disabled="resetExecuting"
                                            @click="executeReset"
                                        >
                                            <span x-show="!resetExecuting">{{ __('ui.account.reset_now') }}</span>
                                            <span x-show="resetExecuting">{{ __('ui.account.resetting') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-7 flex justify-end" x-show="!resetConfirmationOpen">
                                    <button
                                        type="button"
                                        class="button button-primary"
                                        :disabled="!resetPreview.eligible || !resetPreviewConfiguration.writesEnabled || resetExecuting"
                                        @click="openResetConfirmation"
                                    >
                                        {{ __('ui.account.reset_confirm') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </article>
                </div>

                <div class="mt-5" x-show="activeSection === 'grand-reset'" x-cloak>
                    <article class="panel-card">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="kicker">{{ __('ui.account.transactional_operation') }}</p>
                                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.grand_reset_preview') }}</h3>
                            </div>
                            <span class="preview-badge">{{ __('ui.account.preview_only') }}</span>
                        </div>

                        <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" x-show="grandResetPreviewLoading">
                            @for ($placeholder = 0; $placeholder < 4; $placeholder++)
                                <div class="outcome-card animate-pulse" aria-hidden="true">
                                    <span class="h-3 w-20 rounded bg-white/8"></span>
                                    <strong class="mt-3 h-6 w-28 rounded bg-white/10"></strong>
                                </div>
                            @endfor
                        </div>

                        <div
                            class="mt-7 rounded-2xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100"
                            x-show="grandResetPreviewError"
                            x-text="grandResetPreviewError"
                            role="alert"
                        ></div>

                        <div
                            class="mt-7 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 p-4 text-sm text-emerald-100"
                            x-show="grandResetSuccessMessage"
                            x-text="grandResetSuccessMessage"
                            role="status"
                        ></div>

                        <template x-if="grandResetPreview !== null && !grandResetPreviewLoading">
                            <div>
                                <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.level') }}</span>
                                        <strong><span x-text="grandResetPreview.currentLevel"></span> → <span x-text="grandResetPreview.resultingLevel"></span></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.resets') }}</span>
                                        <strong><span x-text="grandResetPreview.currentResets"></span> → <span x-text="grandResetPreview.resultingResets"></span></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.grand') }}</span>
                                        <strong><span x-text="grandResetPreview.currentGrandResets"></span> → <span x-text="grandResetPreview.resultingGrandResets"></span></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.inventory') }}</span>
                                        <strong x-text="grandResetInventoryPolicyLabel"></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.cost') }}</span>
                                        <strong><span x-text="grandResetPreview.zenCost.toLocaleString()"></span> Zen</strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.zen_after_grand_reset') }}</span>
                                        <strong><span x-text="grandResetPreview.resultingZen.toLocaleString()"></span> Zen</strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.grand_reset_points') }}</span>
                                        <strong x-text="grandResetPreview.resultingPoints.toLocaleString()"></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.grand_reset_required_level') }}</span>
                                        <strong x-text="grandResetPreview.requiredLevel.toLocaleString()"></strong>
                                    </div>
                                    <div class="outcome-card">
                                        <span>{{ __('ui.account.grand_reset_required_resets') }}</span>
                                        <strong x-text="grandResetPreview.requiredResets.toLocaleString()"></strong>
                                    </div>
                                </div>

                                <div
                                    class="mt-6 rounded-2xl border p-4 text-sm leading-6"
                                    :class="grandResetPreview.eligible
                                        ? 'border-emerald-300/20 bg-emerald-300/7 text-emerald-100'
                                        : 'border-amber-300/20 bg-amber-300/7 text-amber-100'"
                                >
                                    <strong
                                        class="block"
                                        x-text="grandResetPreview.eligible
                                            ? @js(__('ui.account.grand_reset_requirements_met'))
                                            : @js(__('ui.account.grand_reset_requirements_failed'))"
                                    ></strong>
                                    <ul class="mt-2 grid gap-1" x-show="!grandResetPreview.eligible">
                                        <template x-for="blockMessage in grandResetBlockMessages" :key="blockMessage">
                                            <li>• <span x-text="blockMessage"></span></li>
                                        </template>
                                    </ul>
                                </div>

                                <div class="mt-6 rounded-2xl border border-white/10 bg-white/4 p-4 text-sm leading-6 text-silver">
                                    <span x-show="resetPreviewConfiguration.writesEnabled">{{ __('ui.account.grand_reset_preview_notice') }}</span>
                                    <span x-show="!resetPreviewConfiguration.writesEnabled">{{ __('ui.account.reset_writes_disabled') }}</span>
                                </div>

                                <div
                                    class="mt-6 rounded-2xl border border-gold/20 bg-gold/5 p-5"
                                    x-show="grandResetConfirmationOpen"
                                    x-cloak
                                >
                                    <strong class="block text-white">{{ __('ui.account.grand_reset_confirm') }}</strong>
                                    <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.grand_reset_confirm_help') }}</p>
                                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                                        <button
                                            type="button"
                                            class="button button-ghost"
                                            :disabled="grandResetExecuting"
                                            @click="closeGrandResetConfirmation"
                                        >{{ __('ui.account.reset_cancel') }}</button>
                                        <button
                                            type="button"
                                            class="button button-primary"
                                            :disabled="grandResetExecuting"
                                            @click="executeGrandReset"
                                        >
                                            <span x-show="!grandResetExecuting">{{ __('ui.account.grand_reset_now') }}</span>
                                            <span x-show="grandResetExecuting">{{ __('ui.account.grand_resetting') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-7 flex justify-end" x-show="!grandResetConfirmationOpen">
                                    <button
                                        type="button"
                                        class="button button-primary"
                                        :disabled="!grandResetPreview.eligible || !resetPreviewConfiguration.writesEnabled || grandResetExecuting"
                                        @click="openGrandResetConfirmation"
                                    >
                                        {{ __('ui.account.grand_reset_confirm') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </article>
                </div>
            </section>
        </div>
        @endif
    </div>
@endsection
