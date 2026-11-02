<div class="mt-5" x-show="activeSection === 'move-to-town'" x-cloak>
    <article class="panel-card">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="kicker">{{ __('ui.account.transactional_operation') }}</p>
                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.move_to_town_preview') }}</h3>
                <p class="mt-2 text-sm text-steel">{{ __('ui.account.move_to_town_help') }}</p>
            </div>
            <span class="preview-badge">{{ __('ui.account.preview_only') }}</span>
        </div>

        <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" x-show="moveToTownPreviewLoading">
            @for ($placeholder = 0; $placeholder < 4; $placeholder++)
                <div class="outcome-card animate-pulse" aria-hidden="true"><span class="h-3 w-20 rounded bg-white/8"></span><strong class="mt-3 h-6 w-28 rounded bg-white/10"></strong></div>
            @endfor
        </div>

        <div class="mt-7 rounded-2xl border border-red-300/20 bg-red-400/8 p-4 text-sm text-red-100" x-show="moveToTownPreviewError" x-text="moveToTownPreviewError" role="alert"></div>
        <div class="mt-7 rounded-2xl border border-emerald-300/20 bg-emerald-300/7 p-4 text-sm text-emerald-100" x-show="moveToTownSuccessMessage" x-text="moveToTownSuccessMessage" role="status"></div>

        <template x-if="moveToTownPreview !== null && !moveToTownPreviewLoading">
            <div>
                <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="outcome-card"><span>{{ __('ui.account.current_location') }}</span><strong><span x-text="moveToTownPreview.currentMapName"></span> · <span x-text="moveToTownPreview.currentX"></span>, <span x-text="moveToTownPreview.currentY"></span></strong></div>
                    <div class="outcome-card"><span>{{ __('ui.account.destination') }}</span><strong><span x-text="moveToTownPreview.destinationMapName"></span> · <span x-text="moveToTownPreview.destinationX"></span>, <span x-text="moveToTownPreview.destinationY"></span></strong></div>
                    <div class="outcome-card"><span>{{ __('ui.account.cost') }}</span><strong><span x-text="moveToTownPreview.zenCost.toLocaleString()"></span> Zen</strong></div>
                    <div class="outcome-card"><span>{{ __('ui.account.zen_after_move') }}</span><strong x-text="moveToTownPreview.resultingZen.toLocaleString()"></strong></div>
                </div>

                <div class="mt-6 rounded-2xl border p-4 text-sm leading-6" :class="moveToTownPreview.eligible ? 'border-emerald-300/20 bg-emerald-300/7 text-emerald-100' : 'border-amber-300/20 bg-amber-300/7 text-amber-100'">
                    <strong class="block" x-text="moveToTownPreview.eligible ? @js(__('ui.account.move_to_town_requirements_met')) : @js(__('ui.account.move_to_town_requirements_failed'))"></strong>
                    <ul class="mt-2 grid gap-1" x-show="!moveToTownPreview.eligible">
                        <template x-for="blockMessage in moveToTownBlockMessages" :key="blockMessage"><li>• <span x-text="blockMessage"></span></li></template>
                    </ul>
                </div>

                <div class="mt-6 rounded-2xl border border-white/10 bg-white/4 p-4 text-sm leading-6 text-silver">
                    <span x-show="resetPreviewConfiguration.writesEnabled">{{ __('ui.account.move_to_town_preview_notice') }}</span>
                    <span x-show="!resetPreviewConfiguration.writesEnabled">{{ __('ui.account.reset_writes_disabled') }}</span>
                </div>

                <div class="mt-6 rounded-2xl border border-gold/20 bg-gold/5 p-5" x-show="moveToTownConfirmationOpen" x-cloak>
                    <strong class="block text-white">{{ __('ui.account.move_to_town_confirm') }}</strong>
                    <p class="mt-2 text-sm leading-6 text-silver">{{ __('ui.account.move_to_town_confirm_help') }}</p>
                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                        <button type="button" class="button button-ghost" :disabled="moveToTownExecuting" @click="closeMoveToTownConfirmation">{{ __('ui.account.reset_cancel') }}</button>
                        <button type="button" class="button button-primary" :disabled="moveToTownExecuting" @click="executeMoveToTown">
                            <span x-show="!moveToTownExecuting">{{ __('ui.account.move_to_town_now') }}</span>
                            <span x-show="moveToTownExecuting">{{ __('ui.account.moving_to_town') }}</span>
                        </button>
                    </div>
                </div>

                <div class="mt-7 flex justify-end" x-show="!moveToTownConfirmationOpen">
                    <button type="button" class="button button-primary" :disabled="!moveToTownPreview.eligible || !resetPreviewConfiguration.writesEnabled || moveToTownExecuting" @click="openMoveToTownConfirmation">{{ __('ui.account.move_to_town_confirm') }}</button>
                </div>
            </div>
        </template>
    </article>
</div>
