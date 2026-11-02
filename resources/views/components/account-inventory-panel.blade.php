<div {{ $attributes->class(['mt-5']) }} x-show="activeSection === 'inventory'" x-cloak>
    <article class="panel-card">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="kicker">{{ __('ui.account.decoded_grid') }}</p>
                <h3 class="mt-2 text-xl font-semibold text-white">{{ __('ui.account.inventory') }}</h3>
            </div>
            <p class="text-sm text-steel">{{ __('ui.account.item_details') }}</p>
        </div>
        <p class="mt-6 text-sm text-steel" x-show="itemStorageLoading">{{ __('ui.account.item_storage_loading') }}</p>
        <p class="mt-6 text-sm text-crimson" x-show="itemStorageError" x-text="itemStorageError"></p>

        <template x-if="itemStorage !== null && !itemStorageLoading">
            <div>
                <p class="kicker mt-7">{{ __('ui.account.equipment') }}</p>
                <x-equipment-layout
                    class="mt-3"
                    items="itemStorage.character.equipment"
                />

                <p class="kicker mt-7">{{ __('ui.account.inventory_bag') }}</p>
                <x-item-grid
                    class="mt-3"
                    :slots="64"
                    items="itemStorage.character.inventory"
                />

                <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="kicker">{{ __('ui.account.warehouse') }}</p>
                        <p class="mt-2 text-sm text-steel">
                            {{ __('ui.account.warehouse_zen') }}:
                            <strong class="text-white" x-text="itemStorage.warehouse.zen.toLocaleString()"></strong>
                        </p>
                    </div>
                    <div class="segmented-control" x-show="itemStorage.warehouse.vaults.length > 1">
                        <template x-for="vault in itemStorage.warehouse.vaults" :key="vault.number">
                            <button
                                type="button"
                                :class="selectedVaultNumber === vault.number && 'segmented-control-active'"
                                @click="selectWarehouseVault(vault.number)"
                                x-text="`${resetPreviewConfiguration.vaultLabel} ${vault.number}`"
                            ></button>
                        </template>
                    </div>
                </div>
                <p class="mt-4 text-sm text-steel" x-show="selectedWarehouseVault === null">{{ __('ui.account.no_warehouse') }}</p>
                <x-item-grid
                    class="mt-4"
                    :slots="120"
                    items="selectedWarehouseVault?.items ?? []"
                    x-show="selectedWarehouseVault !== null"
                />
            </div>
        </template>
    </article>
</div>
