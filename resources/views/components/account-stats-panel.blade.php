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
