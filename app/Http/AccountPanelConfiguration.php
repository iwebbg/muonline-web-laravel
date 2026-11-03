<?php

namespace App\Http;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Translation\Translator;

/**
 * Builds the server-authoritative URLs and translated labels consumed by the account panel.
 */
final class AccountPanelConfiguration
{
    /**
     * Creates the account panel configuration with framework URL and translation services.
     */
    public function __construct(
        private readonly UrlGenerator $urlGenerator,
        private readonly Translator $translator,
    ) {}

    /**
     * Builds the locale-aware client configuration for one authenticated request.
     *
     * @return array<string, mixed>
     */
    public function toArray(bool $writesEnabled, string $csrfToken): array
    {
        return [
            ...$this->routes(),
            'writesEnabled' => $writesEnabled,
            'csrfToken' => $csrfToken,
            ...$this->statusMessages(),
            'itemOptionLabels' => $this->itemOptionLabels(),
            'inventoryPolicies' => $this->inventoryPolicies(),
            'blockReasons' => $this->resetBlockReasons(),
            'executionReasons' => $this->resetExecutionReasons(),
            'grandResetBlockReasons' => $this->grandResetBlockReasons(),
            'statResetBlockReasons' => $this->statResetBlockReasons(),
            'pkClearBlockReasons' => $this->pkClearBlockReasons(),
            'pkClearPriceModes' => $this->pkClearPriceModes(),
            'moveToTownBlockReasons' => $this->moveToTownBlockReasons(),
            'statBlockReasons' => $this->statBlockReasons(),
        ];
    }

    /**
     * Builds the named endpoint map consumed by account operations.
     *
     * @return array<string, string>
     */
    private function routes(): array
    {
        return [
            'previewUrl' => $this->urlGenerator->route('account.reset-preview'),
            'executeUrl' => $this->urlGenerator->route('account.character-reset'),
            'grandResetPreviewUrl' => $this->urlGenerator->route('account.grand-reset-preview'),
            'grandResetExecuteUrl' => $this->urlGenerator->route('account.character-grand-reset'),
            'statResetPreviewUrl' => $this->urlGenerator->route('account.stat-reset-preview'),
            'statResetExecuteUrl' => $this->urlGenerator->route('account.character-stat-reset'),
            'pkClearPreviewUrl' => $this->urlGenerator->route('account.pk-clear-preview'),
            'pkClearExecuteUrl' => $this->urlGenerator->route('account.character-pk-clear'),
            'moveToTownPreviewUrl' => $this->urlGenerator->route('account.move-to-town-preview'),
            'moveToTownExecuteUrl' => $this->urlGenerator->route('account.character-move'),
            'statPreviewUrl' => $this->urlGenerator->route('account.stat-allocation-preview'),
            'statExecuteUrl' => $this->urlGenerator->route('account.stat-allocations.store'),
            'itemStorageUrl' => $this->urlGenerator->route('account.item-storage'),
        ];
    }

    /**
     * Builds translated request and completion messages for every operation.
     *
     * @return array<string, string>
     */
    private function statusMessages(): array
    {
        return [
            'previewFailed' => $this->translate('ui.account.reset_preview_failed'),
            'executionFailed' => $this->translate('ui.account.reset_operation_failed'),
            'completed' => $this->translate('ui.account.reset_completed'),
            'grandResetPreviewFailed' => $this->translate('ui.account.grand_reset_preview_failed'),
            'grandResetExecutionFailed' => $this->translate('ui.account.grand_reset_operation_failed'),
            'grandResetCompleted' => $this->translate('ui.account.grand_reset_completed'),
            'statResetPreviewFailed' => $this->translate('ui.account.stat_reset_preview_failed'),
            'statResetExecutionFailed' => $this->translate('ui.account.stat_reset_operation_failed'),
            'statResetCompleted' => $this->translate('ui.account.stat_reset_completed'),
            'pkClearPreviewFailed' => $this->translate('ui.account.pk_clear_preview_failed'),
            'pkClearExecutionFailed' => $this->translate('ui.account.pk_clear_operation_failed'),
            'pkClearCompleted' => $this->translate('ui.account.pk_clear_completed'),
            'moveToTownPreviewFailed' => $this->translate('ui.account.move_to_town_preview_failed'),
            'moveToTownExecutionFailed' => $this->translate('ui.account.move_to_town_operation_failed'),
            'moveToTownCompleted' => $this->translate('ui.account.move_to_town_completed'),
            'statPreviewFailed' => $this->translate('ui.account.allocation_preview_failed'),
            'statExecutionFailed' => $this->translate('ui.account.stat_allocation_failed'),
            'statCompleted' => $this->translate('ui.account.stat_allocation_completed'),
            'itemStorageFailed' => $this->translate('ui.account.item_storage_failed'),
            'vaultLabel' => $this->translate('ui.account.vault'),
            'mainVaultLabel' => $this->translate('ui.account.main_vault'),
        ];
    }

    /**
     * Builds translated labels used by the decoded item tooltip.
     *
     * @return array<string, mixed>
     */
    private function itemOptionLabels(): array
    {
        return [
            'durability' => $this->translate('ui.account.item_durability'),
            'skill' => $this->translate('ui.account.item_skill'),
            'luckSoul' => $this->translate('ui.account.item_luck_soul'),
            'luckCritical' => $this->translate('ui.account.item_luck_critical'),
            'additionalDamage' => $this->translate('ui.account.item_additional_damage'),
            'additionalDefense' => $this->translate('ui.account.item_additional_defense'),
            'additionalDefenseRate' => $this->translate('ui.account.item_additional_defense_rate'),
            'automaticHpRecovery' => $this->translate('ui.account.item_automatic_hp_recovery'),
            'ancientOption' => $this->translate('ui.account.item_ancient_option'),
            'excellentPrefix' => $this->translate('ui.account.item_excellent_prefix'),
            'excellent' => [
                'weapon' => [
                    $this->translate('ui.account.item_excellent_weapon_mana'),
                    $this->translate('ui.account.item_excellent_weapon_life'),
                    $this->translate('ui.account.item_excellent_weapon_speed'),
                    $this->translate('ui.account.item_excellent_weapon_damage_percent'),
                    $this->translate('ui.account.item_excellent_weapon_damage_level'),
                    $this->translate('ui.account.item_excellent_weapon_rate'),
                ],
                'armor' => [
                    $this->translate('ui.account.item_excellent_armor_zen'),
                    $this->translate('ui.account.item_excellent_armor_success_rate'),
                    $this->translate('ui.account.item_excellent_armor_reflect'),
                    $this->translate('ui.account.item_excellent_armor_damage_decrease'),
                    $this->translate('ui.account.item_excellent_armor_mana'),
                    $this->translate('ui.account.item_excellent_armor_life'),
                ],
                'wing' => [
                    $this->translate('ui.account.item_excellent_wing_life'),
                    $this->translate('ui.account.item_excellent_wing_mana'),
                    $this->translate('ui.account.item_excellent_wing_mana_loss'),
                    $this->translate('ui.account.item_excellent_wing_life_transfer'),
                    $this->translate('ui.account.item_excellent_wing_speed'),
                    '',
                ],
            ],
        ];
    }

    /**
     * Builds translated reset inventory policy labels.
     *
     * @return array<string, string>
     */
    private function inventoryPolicies(): array
    {
        return [
            'keep_all' => $this->translate('ui.account.inventory_keep_all'),
            'clear_bag' => $this->translate('ui.account.inventory_clear_bag'),
            'clear_all' => $this->translate('ui.account.inventory_clear_all'),
        ];
    }

    /**
     * Builds translated normal-reset preview blockers.
     *
     * @return array<string, string>
     */
    private function resetBlockReasons(): array
    {
        return [
            'profile_disabled' => $this->translate('ui.account.reset_profile_disabled'),
            'character_online' => $this->translate('ui.account.reset_character_online'),
            'level_too_low' => $this->translate('ui.account.reset_level_too_low'),
            'maximum_resets_reached' => $this->translate('ui.account.reset_maximum_reached'),
            'insufficient_zen' => $this->translate('ui.account.reset_insufficient_zen'),
        ];
    }

    /**
     * Builds translated normal-reset execution blockers.
     *
     * @return array<string, string>
     */
    private function resetExecutionReasons(): array
    {
        return [
            'writes_disabled' => $this->translate('ui.account.reset_writes_disabled'),
            'inventory_policy_unsupported' => $this->translate('ui.account.reset_inventory_policy_unsupported'),
            'operation_in_progress' => $this->translate('ui.account.reset_operation_in_progress'),
            'idempotency_conflict' => $this->translate('ui.account.reset_idempotency_conflict'),
            'character_class_unsupported' => $this->translate('ui.account.reset_character_class_unsupported'),
            'character_online' => $this->translate('ui.account.reset_character_online'),
            'level_too_low' => $this->translate('ui.account.reset_level_too_low'),
            'maximum_resets_reached' => $this->translate('ui.account.reset_maximum_reached'),
            'insufficient_zen' => $this->translate('ui.account.reset_insufficient_zen'),
        ];
    }

    /**
     * Builds translated grand-reset blockers.
     *
     * @return array<string, string>
     */
    private function grandResetBlockReasons(): array
    {
        return [
            'profile_disabled' => $this->translate('ui.account.grand_reset_profile_disabled'),
            'character_online' => $this->translate('ui.account.reset_character_online'),
            'level_too_low' => $this->translate('ui.account.grand_reset_level_too_low'),
            'insufficient_resets' => $this->translate('ui.account.grand_reset_insufficient_resets'),
            'maximum_grand_resets_reached' => $this->translate('ui.account.grand_reset_maximum_reached'),
            'insufficient_zen' => $this->translate('ui.account.grand_reset_insufficient_zen'),
            'point_limit_exceeded' => $this->translate('ui.account.grand_reset_point_limit'),
            'inventory_policy_unsupported' => $this->translate('ui.account.reset_inventory_policy_unsupported'),
            'character_class_unsupported' => $this->translate('ui.account.reset_character_class_unsupported'),
            'writes_disabled' => $this->translate('ui.account.reset_writes_disabled'),
            'operation_in_progress' => $this->translate('ui.account.grand_reset_operation_in_progress'),
            'idempotency_conflict' => $this->translate('ui.account.grand_reset_idempotency_conflict'),
        ];
    }

    /**
     * Builds translated stat-reset blockers.
     *
     * @return array<string, string>
     */
    private function statResetBlockReasons(): array
    {
        return [
            'profile_disabled' => $this->translate('ui.account.stat_reset_profile_disabled'),
            'character_online' => $this->translate('ui.account.reset_character_online'),
            'level_too_low' => $this->translate('ui.account.stat_reset_level_too_low'),
            'insufficient_resets' => $this->translate('ui.account.stat_reset_insufficient_resets'),
            'insufficient_zen' => $this->translate('ui.account.stat_reset_insufficient_zen'),
            'no_allocated_stats' => $this->translate('ui.account.stat_reset_no_allocated_stats'),
            'character_stats_invalid' => $this->translate('ui.account.stat_reset_character_stats_invalid'),
            'point_limit_exceeded' => $this->translate('ui.account.stat_reset_point_limit'),
            'character_class_unsupported' => $this->translate('ui.account.reset_character_class_unsupported'),
            'writes_disabled' => $this->translate('ui.account.reset_writes_disabled'),
            'operation_in_progress' => $this->translate('ui.account.stat_reset_operation_in_progress'),
            'idempotency_conflict' => $this->translate('ui.account.stat_reset_idempotency_conflict'),
        ];
    }

    /**
     * Builds translated PK-clear blockers.
     *
     * @return array<string, string>
     */
    private function pkClearBlockReasons(): array
    {
        return [
            'profile_disabled' => $this->translate('ui.account.pk_clear_profile_disabled'),
            'character_online' => $this->translate('ui.account.reset_character_online'),
            'level_too_low' => $this->translate('ui.account.pk_clear_level_too_low'),
            'character_not_killer' => $this->translate('ui.account.pk_clear_not_killer'),
            'price_limit_exceeded' => $this->translate('ui.account.pk_clear_price_limit'),
            'insufficient_zen' => $this->translate('ui.account.pk_clear_insufficient_zen'),
            'writes_disabled' => $this->translate('ui.account.reset_writes_disabled'),
            'operation_in_progress' => $this->translate('ui.account.pk_clear_operation_in_progress'),
            'idempotency_conflict' => $this->translate('ui.account.pk_clear_idempotency_conflict'),
        ];
    }

    /**
     * Builds translated PK-clear price strategy labels.
     *
     * @return array<string, string>
     */
    private function pkClearPriceModes(): array
    {
        return [
            'fixed' => $this->translate('ui.account.pk_clear_price_fixed'),
            'by_pk_level' => $this->translate('ui.account.pk_clear_price_by_level'),
            'by_pk_count' => $this->translate('ui.account.pk_clear_price_by_count'),
        ];
    }

    /**
     * Builds translated move-to-town blockers.
     *
     * @return array<string, string>
     */
    private function moveToTownBlockReasons(): array
    {
        return [
            'profile_disabled' => $this->translate('ui.account.move_to_town_profile_disabled'),
            'character_online' => $this->translate('ui.account.reset_character_online'),
            'insufficient_zen' => $this->translate('ui.account.move_to_town_insufficient_zen'),
            'already_at_destination' => $this->translate('ui.account.move_to_town_already_there'),
            'writes_disabled' => $this->translate('ui.account.reset_writes_disabled'),
            'operation_in_progress' => $this->translate('ui.account.move_to_town_operation_in_progress'),
            'idempotency_conflict' => $this->translate('ui.account.move_to_town_idempotency_conflict'),
        ];
    }

    /**
     * Builds translated stat-allocation blockers.
     *
     * @return array<string, string>
     */
    private function statBlockReasons(): array
    {
        return [
            'character_online' => $this->translate('ui.account.reset_character_online'),
            'no_points_allocated' => $this->translate('ui.account.allocation_no_points'),
            'invalid_allocation' => $this->translate('ui.account.allocation_invalid'),
            'insufficient_points' => $this->translate('ui.account.allocation_insufficient_points'),
            'stat_limit_exceeded' => $this->translate('ui.account.allocation_stat_limit'),
            'character_class_unsupported' => $this->translate('ui.account.allocation_class_unsupported'),
            'writes_disabled' => $this->translate('ui.account.reset_writes_disabled'),
            'operation_in_progress' => $this->translate('ui.account.reset_operation_in_progress'),
            'idempotency_conflict' => $this->translate('ui.account.reset_idempotency_conflict'),
        ];
    }

    /**
     * Resolves one user-facing translation and fails safely to its key.
     */
    private function translate(string $key): string
    {
        $translation = $this->translator->get($key);

        return is_string($translation) ? $translation : $key;
    }
}
