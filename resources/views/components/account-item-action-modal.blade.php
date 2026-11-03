<template x-teleport="body">
    <div
        class="fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto bg-black/85 p-4 backdrop-blur-sm sm:p-6"
        x-show="itemActionModalOpen"
        x-transition.opacity
        x-cloak
        @click.self="closeItemActions()"
        @keydown.escape.window="closeItemActions()"
    >
        <section class="panel-card w-full max-w-3xl shadow-2xl shadow-black/70" role="dialog" aria-modal="true" aria-labelledby="item-action-title">
            <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                <div>
                    <p class="kicker">{{ __('ui.account.item_actions_kicker') }}</p>
                    <h2 id="item-action-title" class="mt-2 text-2xl font-semibold text-white">{{ __('ui.account.item_actions_heading') }}</h2>
                </div>
                <button type="button" class="icon-button" aria-label="{{ __('ui.account.item_actions_close') }}" @click="closeItemActions()">×</button>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-[15rem_1fr]" x-show="selectedActionItem !== null">
                <div class="item-action-preview rounded-2xl border border-white/8 bg-black/25 p-5 text-center">
                    <div class="grid min-h-44 place-items-center">
                        <template x-if="selectedActionItem?.image !== null">
                            <img :src="selectedActionItem?.image" :alt="selectedActionItem?.name" class="max-h-40 max-w-full object-contain">
                        </template>
                    </div>
                    <strong class="mt-4 block text-lg" :class="`item-name-${itemNameTone(selectedActionItem)}`" x-text="itemTitle(selectedActionItem)"></strong>
                    <div class="mt-4 text-sm">
                        <x-item-tooltip item="selectedActionItem" />
                    </div>
                </div>

                <div class="grid content-start gap-4">
                    <p class="text-sm leading-6 text-silver">{{ __('ui.account.item_actions_help') }}</p>

                    <p class="rounded-xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100" x-show="selectedItemLocation === 'warehouse' && selectedCharacter === undefined">
                        {{ __('ui.account.item_actions_character_required') }}
                    </p>

                    <form method="POST" :action="resetPreviewConfiguration.marketListingStoreUrl" class="rounded-2xl border border-white/8 bg-white/[0.025] p-4" x-show="selectedItemLocation === 'warehouse' && selectedCharacter !== undefined && resetPreviewConfiguration.marketEnabled">
                        @csrf
                        <input type="hidden" name="character_name" :value="selectedCharacter?.name ?? ''">
                        <input type="hidden" name="vault" :value="selectedVaultNumber">
                        <input type="hidden" name="slot" :value="selectedActionItem?.slot">
                        <input type="hidden" name="idempotency_key" :value="itemActionIdempotencyKey">
                        <label class="field-group">
                            <span>{{ __('ui.market.sale_type') }}</span>
                            <select name="sale_type" x-model="itemSaleType">
                                <option value="fixed">{{ __('ui.market.fixed_price') }}</option>
                                <option value="auction" x-show="resetPreviewConfiguration.auctionEnabled">{{ __('ui.market.auction') }}</option>
                            </select>
                        </label>
                        <label class="field-group mt-3">
                            <span x-text="itemSaleType === 'auction' ? @js(__('ui.market.minimum_bid')) : @js(__('ui.market.price_credits'))"></span>
                            <input type="number" name="price_credits" min="1" max="1000000000" value="100" required>
                        </label>
                        <label class="field-group mt-3" x-show="itemSaleType === 'auction'">
                            <span>{{ __('ui.market.bid_increment') }}</span>
                            <input type="number" name="bid_increment_credits" min="1" max="1000000000" value="10" :required="itemSaleType === 'auction'">
                        </label>
                        <button type="submit" class="button button-primary mt-4 w-full" :disabled="!resetPreviewConfiguration.writesEnabled">{{ __('ui.account.item_sell_market') }}</button>
                    </form>

                    <form method="POST" :action="resetPreviewConfiguration.webStorageStoreUrl" x-show="selectedItemLocation === 'warehouse' && selectedCharacter !== undefined && resetPreviewConfiguration.webStorageEnabled">
                        @csrf
                        <input type="hidden" name="character_name" :value="selectedCharacter?.name ?? ''">
                        <input type="hidden" name="vault" :value="selectedVaultNumber">
                        <input type="hidden" name="slot" :value="selectedActionItem?.slot">
                        <input type="hidden" name="idempotency_key" :value="itemActionIdempotencyKey">
                        <button type="submit" class="button button-ghost w-full" :disabled="!resetPreviewConfiguration.writesEnabled">{{ __('ui.web_storage.store') }}</button>
                    </form>

                    <form method="POST" :action="selectedWebStorageItem?.withdrawUrl" x-show="selectedItemLocation === 'web'">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-primary w-full" :disabled="!resetPreviewConfiguration.writesEnabled">{{ __('ui.account.item_move_game_vault') }}</button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</template>
