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
                    <div class="outcome-card"><span>{{ __('ui.account.cost') }}</span><strong><span x-text="pkClearPreview.zenCost.toLocaleString()"></span><small class="outcome-unit">Zen</small></strong></div>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="outcome-card"><span>{{ __('ui.account.zen_after_pk_clear') }}</span><strong><span x-text="pkClearPreview.resultingZen.toLocaleString()"></span><small class="outcome-unit">Zen</small></strong></div>
                    <div class="outcome-card"><span>{{ __('ui.account.pk_clear_price_formula') }}</span><strong x-text="pkClearPriceModeLabel"></strong></div>
                    <div class="outcome-card"><span>{{ __('ui.account.required_level') }}</span><strong x-text="pkClearPreview.requiredLevel.toLocaleString()"></strong></div>
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
