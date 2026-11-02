<div {{ $attributes->class(['mt-5']) }} x-show="activeSection === 'overview'">
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
                <button type="button" class="action-row" @click="selectSection('move-to-town')">
                    <span><strong>{{ __('ui.account.move_to_town') }}</strong><small>{{ __('ui.account.offline_only') }}</small></span>
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </article>
    </div>
</div>
