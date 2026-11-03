<div {{ $attributes->class(['mt-5']) }} x-show="activeSection === 'vaults'" x-cloak>
    @if (session('marketStatus') || session('webStorageStatus'))
        <div class="mb-5 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
            {{ session('marketStatus') ?? session('webStorageStatus') }}
        </div>
    @endif
    @if ($errors->has('market') || $errors->has('webStorage'))
        <div class="mb-5 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
            {{ $errors->first('market') ?? $errors->first('webStorage') }}
        </div>
    @endif

    <article class="panel-card">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="kicker">{{ __('ui.account.account_storage') }}</p>
                <h3 class="mt-2 text-2xl font-semibold text-white">{{ __('ui.account.vaults') }}</h3>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-silver">{{ __('ui.account.vaults_help') }}</p>
            </div>
            <p class="text-sm text-steel" x-show="itemStorage !== null">
                {{ __('ui.account.warehouse_zen') }}:
                <strong class="text-white" x-text="itemStorage?.warehouse.zen.toLocaleString()"></strong>
            </p>
        </div>

        <p class="mt-6 text-sm text-steel" x-show="itemStorageLoading">{{ __('ui.account.item_storage_loading') }}</p>
        <p class="mt-6 text-sm text-crimson" x-show="itemStorageError" x-text="itemStorageError"></p>

        <template x-if="itemStorage !== null && !itemStorageLoading">
            <div>
                <div class="mt-7 flex flex-wrap items-center justify-between gap-3">
                    <p class="kicker">{{ __('ui.account.game_vaults') }}</p>
                    <div class="segmented-control" x-show="itemStorage.warehouse.vaults.length > 1">
                        <template x-for="vault in itemStorage.warehouse.vaults" :key="vault.number">
                            <button
                                type="button"
                                :class="selectedVaultNumber === vault.number && 'segmented-control-active'"
                                @click="selectWarehouseVault(vault.number)"
                                x-text="vault.active ? resetPreviewConfiguration.mainVaultLabel : `${resetPreviewConfiguration.vaultLabel} ${vault.number}`"
                            ></button>
                        </template>
                    </div>
                </div>
                <p class="mt-4 text-sm text-steel" x-show="selectedWarehouseVault === null">{{ __('ui.account.no_warehouse') }}</p>
                <x-item-grid
                    class="mt-4"
                    :slots="120"
                    items="selectedWarehouseVault?.items ?? []"
                    item-action="openWarehouseItemActions"
                    x-show="selectedWarehouseVault !== null"
                />

                <template x-if="resetPreviewConfiguration.webStorageEnabled">
                    <div class="mt-8 border-t border-white/8 pt-8">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p class="kicker">{{ __('ui.web_storage.kicker') }}</p>
                                <h4 class="mt-2 text-lg font-semibold text-white">{{ __('ui.web_storage.stored_items') }}</h4>
                            </div>
                            <span class="text-sm text-steel" x-text="resetPreviewConfiguration.webStorageItems.length"></span>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" x-show="resetPreviewConfiguration.webStorageItems.length > 0">
                            <template x-for="storedItem in resetPreviewConfiguration.webStorageItems" :key="storedItem.publicId">
                                <button type="button" class="warehouse-action-card" @click="openWebStorageItemActions(storedItem)">
                                    <template x-if="storedItem.item.image !== null">
                                        <img :src="storedItem.item.image" :alt="storedItem.item.name" class="size-14 object-contain">
                                    </template>
                                    <span class="min-w-0 text-left">
                                        <strong class="block truncate" :class="`item-name-${itemNameTone(storedItem.item)}`" x-text="itemTitle(storedItem.item)"></strong>
                                        <small class="mt-1 block text-steel" x-text="storedItem.item.code"></small>
                                    </span>
                                    <x-item-tooltip item="storedItem.item" />
                                </button>
                            </template>
                        </div>
                        <p class="mt-4 text-sm text-steel" x-show="resetPreviewConfiguration.webStorageItems.length === 0">{{ __('ui.web_storage.empty') }}</p>
                    </div>
                </template>
            </div>
        </template>
    </article>

    <x-account-item-action-modal />
</div>
