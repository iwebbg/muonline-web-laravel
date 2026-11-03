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
                        <strong><span x-text="resetPreview.zenCost.toLocaleString()"></span><small class="outcome-unit">Zen</small></strong>
                    </div>
                    <div class="outcome-card">
                        <span>{{ __('ui.account.zen_after_reset') }}</span>
                        <strong><span x-text="resetPreview.resultingZen.toLocaleString()"></span><small class="outcome-unit">Zen</small></strong>
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
