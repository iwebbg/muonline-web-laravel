@props([
    'economySettings',
    'characterServicesEnabled',
])

<div {{ $attributes->class(['mt-5']) }} x-show="activeSection === 'overview'">
    <div class="grid gap-4 sm:grid-cols-2">
        <article class="panel-card">
            <p class="kicker">{{ __('ui.account.core_attributes') }}</p>
            <div class="mt-6 grid gap-4">
                <template x-for="statDefinition in selectedCharacter.statDefinitions" :key="statDefinition.key">
                    <div class="stat-line">
                        <span x-text="statDefinition.label"></span>
                        <strong x-text="selectedCharacter.stats[statDefinition.key].toLocaleString()"></strong>
                    </div>
                </template>
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
                <button type="button" class="action-row" @click="selectSection('move-to-town')">
                    <span><strong>{{ __('ui.account.move_to_town') }}</strong><small>{{ __('ui.account.offline_only') }}</small></span>
                    <span aria-hidden="true">→</span>
                </button>
                @if ($characterServicesEnabled && $economySettings->characterRenameEnabled)
                    <button type="button" class="action-row" @click="openCharacterService('rename')">
                        <span><strong>{{ __('ui.account.character_rename') }}</strong><small>{{ number_format($economySettings->characterRenamePriceCredits) }} {{ __('ui.account.wallet_credits') }} · {{ __('ui.account.offline_only') }}</small></span>
                        <span aria-hidden="true">→</span>
                    </button>
                @endif
                @if ($characterServicesEnabled && $economySettings->characterClassChangeEnabled)
                    <button type="button" class="action-row" @click="openCharacterService('class-change')">
                        <span><strong>{{ __('ui.account.character_class_change') }}</strong><small>{{ number_format($economySettings->characterClassChangePriceCredits) }} {{ __('ui.account.wallet_credits') }} · {{ __('ui.account.offline_only') }}</small></span>
                        <span aria-hidden="true">→</span>
                    </button>
                @endif
            </div>
        </article>
    </div>
</div>
