@props([
    'catalog',
    'showPrices' => false,
])

<div x-data="itemBrowser({ catalog: () => Object.values({{ $catalog }}), showPrices: @js($showPrices) })" x-id="['item-browser-title']" class="shrink-0">
    <button type="button" class="button button-ghost" x-ref="browserTrigger" x-on:click="openBrowser()" aria-haspopup="dialog">
        {{ __('ui.item_browser.browse') }}
    </button>

    <template x-teleport="body">
        <dialog
            x-ref="browserDialog"
            x-bind:aria-labelledby="$id('item-browser-title')"
            x-on:cancel.prevent="closeBrowser()"
            x-on:keydown.escape.stop
            x-on:click="if ($event.target === $el) { closeBrowser(); }"
            class="fixed inset-0 m-auto h-[calc(100dvh-1rem)] max-h-none min-w-0 w-[calc(100dvw-1rem)] max-w-[calc(100dvw-1rem)] overflow-hidden rounded-2xl border border-white/15 bg-[#0b0d12] p-0 text-mist shadow-2xl backdrop:bg-black/80 backdrop:backdrop-blur-sm open:flex open:flex-col sm:h-[calc(100dvh-3rem)] sm:w-[calc(100dvw-3rem)] sm:max-w-[calc(100dvw-3rem)]"
        >
            <header class="min-w-0 shrink-0 border-b border-white/10 p-4 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="kicker">{{ __('ui.item_browser.catalog') }}</p>
                        <h2 class="mt-2 text-2xl font-semibold text-white" x-bind:id="$id('item-browser-title')">{{ __('ui.item_browser.heading') }}</h2>
                    </div>
                    <button type="button" class="icon-button shrink-0" x-on:click="closeBrowser()" aria-label="{{ __('ui.item_browser.close') }}">×</button>
                </div>
                <label class="field-group mt-5">
                    <span>{{ __('ui.item_browser.search') }}</span>
                    <input type="search" x-ref="browserSearch" x-model.debounce.150ms="browserQuery" x-on:input="browserPage = 1" placeholder="{{ __('ui.item_browser.placeholder') }}" autocomplete="off">
                </label>
            </header>

            <div class="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain p-4 sm:p-6">
                <template x-if="browserOpen">
                    <div class="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <template x-for="browserItem in browserPageItems" :key="browserItem.code">
                            <article class="flex min-w-0 flex-col rounded-2xl border border-white/10 bg-white/[0.025] p-4">
                                <div class="relative h-44 shrink-0 overflow-hidden rounded-xl border border-white/8 bg-black/30 sm:h-52">
                                    <div class="absolute inset-0 flex items-center justify-center p-6">
                                        <x-item-image source-expression="browserItem.image" label-expression="browserItem.name" class="h-full w-full max-w-48 object-contain" />
                                    </div>
                                </div>
                                <h3 class="mt-4 break-words text-lg font-semibold text-white" x-text="browserItem.name"></h3>
                                <p class="mt-1 text-xs text-steel" x-text="browserItem.code"></p>
                                <p x-show="browserShowPrices && browserItem.basePrice != null" class="mt-3 text-sm text-gold">
                                    {{ __('ui.item_browser.base_price') }}: <strong x-text="Number(browserItem.basePrice).toLocaleString()"></strong> Credits
                                </p>
                                <button type="button" class="button button-primary mt-4 w-full" x-on:click="chooseBrowserItem(browserItem)">{{ __('ui.item_browser.select') }}</button>
                            </article>
                        </template>
                        <p x-show="browserResults.length === 0" class="col-span-full py-16 text-center text-silver" role="status">{{ __('ui.item_browser.empty') }}</p>
                    </div>
                </template>
            </div>

            <footer class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-white/10 p-4 sm:px-6">
                <p class="text-sm text-silver" aria-live="polite"><span x-text="browserResults.length.toLocaleString()"></span> {{ __('ui.item_browser.items') }}</p>
                <div class="flex items-center gap-3">
                    <button type="button" class="button button-ghost" x-on:click="browserPage--; $refs.browserDialog.querySelector('.overflow-y-auto').scrollTop = 0" x-bind:disabled="browserPage <= 1">{{ __('ui.item_browser.previous') }}</button>
                    <span class="text-sm text-silver"><span x-text="browserPage"></span> / <span x-text="browserPageCount"></span></span>
                    <button type="button" class="button button-ghost" x-on:click="browserPage++; $refs.browserDialog.querySelector('.overflow-y-auto').scrollTop = 0" x-bind:disabled="browserPage >= browserPageCount">{{ __('ui.item_browser.next') }}</button>
                </div>
            </footer>
        </dialog>
    </template>
</div>
