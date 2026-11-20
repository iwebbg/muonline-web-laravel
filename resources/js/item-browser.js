/**
 * Browses a version-owned item catalog without replacing the host form's selection rules.
 *
 * @param {{catalog: Function, showPrices: boolean}} configuration Live catalog accessor and price visibility.
 * @returns {object} Alpine state for one independent item search field.
 */
export function itemBrowser(configuration) {
    return {
        browserOpen: false,
        browserQuery: '',
        browserItems: [],
        browserPage: 1,
        browserPageSize: 48,
        browserPreviousOverflow: null,
        browserShowPrices: configuration.showPrices,

        /**
         * Filters by item name or exact catalog code while preserving catalog order.
         *
         * @returns {Array<object>}
         */
        get browserResults() {
            const query = this.browserQuery.trim().toLocaleLowerCase();
            const catalog = this.browserItems;

            if (query === '') {
                return catalog;
            }

            return catalog.filter((item) => `${item.name} ${item.code}`.toLocaleLowerCase().includes(query));
        },

        /**
         * Bounds the number of cards rendered even for large seasonal catalogs.
         *
         * @returns {Array<object>}
         */
        get browserPageItems() {
            const firstItem = (this.browserPage - 1) * this.browserPageSize;

            return this.browserResults.slice(firstItem, firstItem + this.browserPageSize);
        },

        /**
         * Keeps empty search results on one stable page.
         *
         * @returns {number}
         */
        get browserPageCount() {
            return Math.max(1, Math.ceil(this.browserResults.length / this.browserPageSize));
        },

        /**
         * Opens the native top-layer dialog and focuses its independent search field.
         */
        openBrowser() {
            if (this.browserOpen) {
                return;
            }

            this.browserItems = configuration.catalog();
            this.browserQuery = '';
            this.browserPage = 1;
            this.browserOpen = true;
            this.browserPreviousOverflow = document.documentElement.style.overflow;
            document.documentElement.style.overflow = 'hidden';
            this.$nextTick(() => {
                this.$refs.browserDialog.showModal();
                this.$refs.browserSearch.focus();
            });
        },

        /**
         * Closes the dialog and restores the page's previous scroll policy.
         */
        closeBrowser() {
            this.$refs.browserDialog.close();
            this.browserOpen = false;

            if (this.browserPreviousOverflow !== null) {
                document.documentElement.style.overflow = this.browserPreviousOverflow;
                this.browserPreviousOverflow = null;
            }
        },

        /**
         * Sends the original catalog object through the host field's existing selection handler.
         *
         * @param {object} item Verified catalog definition selected from a card.
         */
        chooseBrowserItem(item) {
            this.$refs.browserTrigger.dispatchEvent(new CustomEvent('item-browser-selected', {
                bubbles: true,
                detail: { item },
            }));
            this.closeBrowser();
        },

        /**
         * Releases scroll locking if the host removes an editable offer row.
         */
        destroy() {
            if (this.browserPreviousOverflow !== null) {
                document.documentElement.style.overflow = this.browserPreviousOverflow;
            }
        },
    };
}
