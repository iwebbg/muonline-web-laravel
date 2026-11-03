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
                    <template x-for="statDefinition in selectedCharacter.statDefinitions" :key="statDefinition.key">
                        <div class="outcome-card">
                            <span x-text="statDefinition.label"></span>
                            <strong>
                                <span x-text="statResetPreview.currentStats[statDefinition.key].toLocaleString()"></span>
                                →
                                <span x-text="statResetPreview.resultingStats[statDefinition.key].toLocaleString()"></span>
                            </strong>
                        </div>
                    </template>
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
