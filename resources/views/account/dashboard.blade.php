@inject('moduleManager', 'App\Modules\ModuleManager')
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
                'moveToTownPreviewUrl' => route('account.move-to-town-preview'),
                'moveToTownExecuteUrl' => route('account.character-move'),
                'statPreviewUrl' => route('account.stat-allocation-preview'),
                'statExecuteUrl' => route('account.stat-allocations.store'),
                'itemStorageUrl' => route('account.item-storage'),
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
                'moveToTownPreviewFailed' => __('ui.account.move_to_town_preview_failed'),
                'moveToTownExecutionFailed' => __('ui.account.move_to_town_operation_failed'),
                'moveToTownCompleted' => __('ui.account.move_to_town_completed'),
                'statPreviewFailed' => __('ui.account.allocation_preview_failed'),
                'statExecutionFailed' => __('ui.account.stat_allocation_failed'),
                'statCompleted' => __('ui.account.stat_allocation_completed'),
                'itemStorageFailed' => __('ui.account.item_storage_failed'),
                'vaultLabel' => __('ui.account.vault'),
                'itemOptionLabels' => [
                    'durability' => __('ui.account.item_durability'),
                    'skill' => __('ui.account.item_skill'),
                    'luckSoul' => __('ui.account.item_luck_soul'),
                    'luckCritical' => __('ui.account.item_luck_critical'),
                    'additionalDamage' => __('ui.account.item_additional_damage'),
                    'additionalDefense' => __('ui.account.item_additional_defense'),
                    'additionalDefenseRate' => __('ui.account.item_additional_defense_rate'),
                    'automaticHpRecovery' => __('ui.account.item_automatic_hp_recovery'),
                    'ancientOption' => __('ui.account.item_ancient_option'),
                    'excellentPrefix' => __('ui.account.item_excellent_prefix'),
                    'excellent' => [
                        'weapon' => [
                            __('ui.account.item_excellent_weapon_mana'),
                            __('ui.account.item_excellent_weapon_life'),
                            __('ui.account.item_excellent_weapon_speed'),
                            __('ui.account.item_excellent_weapon_damage_percent'),
                            __('ui.account.item_excellent_weapon_damage_level'),
                            __('ui.account.item_excellent_weapon_rate'),
                        ],
                        'armor' => [
                            __('ui.account.item_excellent_armor_zen'),
                            __('ui.account.item_excellent_armor_success_rate'),
                            __('ui.account.item_excellent_armor_reflect'),
                            __('ui.account.item_excellent_armor_damage_decrease'),
                            __('ui.account.item_excellent_armor_mana'),
                            __('ui.account.item_excellent_armor_life'),
                        ],
                        'wing' => [
                            __('ui.account.item_excellent_wing_life'),
                            __('ui.account.item_excellent_wing_mana'),
                            __('ui.account.item_excellent_wing_mana_loss'),
                            __('ui.account.item_excellent_wing_life_transfer'),
                            __('ui.account.item_excellent_wing_speed'),
                            '',
                        ],
                    ],
                ],
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
                'moveToTownBlockReasons' => [
                    'profile_disabled' => __('ui.account.move_to_town_profile_disabled'),
                    'character_online' => __('ui.account.reset_character_online'),
                    'insufficient_zen' => __('ui.account.move_to_town_insufficient_zen'),
                    'already_at_destination' => __('ui.account.move_to_town_already_there'),
                    'writes_disabled' => __('ui.account.reset_writes_disabled'),
                    'operation_in_progress' => __('ui.account.move_to_town_operation_in_progress'),
                    'idempotency_conflict' => __('ui.account.move_to_town_idempotency_conflict'),
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
            <div class="flex flex-wrap items-stretch gap-3">
                <div class="resource-chip">
                    <span>{{ __('ui.account.signed_in_as') }}</span>
                    <strong>{{ $account->username }}</strong>
                </div>
                @if ($moduleManager->enabled(\App\Modules\ServerModule::Vip))
                    <x-account-vip-modal
                        :characters="$characters"
                        :plans="$vipPlans"
                        :entitlements="$vipEntitlements"
                        :active-entitlement-count="$activeVipEntitlementCount"
                        :purchases="$vipPurchases"
                        :balance="$walletBalance"
                        :idempotency-key="$vipIdempotencyKey"
                        :game-writes-enabled="$gameWritesEnabled"
                    />
                @endif
                <x-account-wallet-modal
                    :balance="$walletBalance"
                    :transactions="$walletTransactions"
                />
                @if ($moduleManager->enabled(\App\Modules\ServerModule::WebStorage))
                <a href="{{ route('account.web-storage.index') }}" class="resource-chip text-left">
                    <span>{{ __('ui.web_storage.kicker') }}</span>
                    <strong class="text-gold">{{ __('ui.web_storage.open') }}</strong>
                </a>
                @endif
                @if ($moduleManager->enabled(\App\Modules\ServerModule::JewelBank))
                <a href="{{ route('account.jewel-bank.index') }}" class="resource-chip text-left">
                    <span>{{ __('ui.jewel_bank.kicker') }}</span>
                    <strong class="text-gold">{{ __('ui.jewel_bank.open') }}</strong>
                </a>
                @endif
                @if ($moduleManager->enabled(\App\Modules\ServerModule::ZenBank))
                <a href="{{ route('account.zen-bank.index') }}" class="resource-chip text-left">
                    <span>{{ __('ui.zen_bank.kicker') }}</span>
                    <strong class="text-gold">{{ __('ui.zen_bank.open') }}</strong>
                </a>
                @endif
                <x-account-credentials-modal
                    :account="$account"
                    :game-writes-enabled="$gameWritesEnabled"
                />
            </div>
        </div>

        @if (session('accountStatus'))
            <div class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 px-5 py-4 text-sm text-emerald-100" role="status">
                {{ session('accountStatus') }}
            </div>
        @endif

        @if ($characters === [])
            <article class="panel-card mt-8 text-center">
                <p class="kicker">{{ __('ui.account.no_characters') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-white">{{ __('ui.account.no_characters_heading') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-silver">{{ __('ui.account.no_characters_help') }}</p>
            </article>
        @else
            <x-account-character-selector class="mt-8" />

            <div class="mt-6 grid gap-6 xl:grid-cols-[22rem_1fr]">
                <x-account-character-portrait />

                <section class="min-w-0">
                    <x-account-character-tabs />

                    <x-account-overview-panel />
                    <x-account-inventory-panel />

                    <x-account-stats-panel />

                    <x-account-stat-reset-panel />

                    <x-account-pk-clear-panel />

                    <x-account-move-to-town-panel />

                    <x-account-reset-panel />

                    <x-account-grand-reset-panel />
                </section>
            </div>
        @endif

    </div>
@endsection
