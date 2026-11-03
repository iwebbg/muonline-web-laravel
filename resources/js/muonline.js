/**
 * Maps legacy equipment slot numbers to their game-style layout regions.
 *
 * Dynamic regions use catalog dimensions while fixed accessory regions preserve
 * the recognizable MuOnline equipment silhouette.
 *
 * @type {Record<number, object>}
 */
const equipmentSlotLayout = {
    0: {
        column: 1,
        row: 3,
        width: 2,
        height: 4,
        horizontal: 'end',
        vertical: 'end',
        dynamic: true,
    },
    1: {
        column: 7,
        row: 3,
        width: 2,
        height: 4,
        horizontal: 'start',
        vertical: 'end',
        dynamic: true,
    },
    2: {
        column: 4,
        row: 1,
        width: 2,
        height: 2,
        horizontal: 'start',
        vertical: 'start',
        dynamic: true,
    },
    3: {
        column: 4,
        row: 3,
        width: 2,
        height: 3,
        horizontal: 'start',
        vertical: 'end',
        dynamic: true,
    },
    4: {
        column: 4,
        row: 7,
        width: 2,
        height: 2,
        horizontal: 'start',
        vertical: 'start',
        dynamic: true,
    },
    5: {
        column: 1,
        row: 7,
        width: 2,
        height: 2,
        horizontal: 'start',
        vertical: 'start',
        dynamic: true,
    },
    6: {
        column: 7,
        row: 7,
        width: 2,
        height: 2,
        horizontal: 'start',
        vertical: 'start',
        dynamic: true,
    },
    7: {
        column: 7,
        row: 1,
        width: 2,
        height: 2,
        horizontal: 'start',
        vertical: 'start',
        dynamic: false,
    },
    8: {
        column: 1,
        row: 1,
        width: 2,
        height: 2,
        horizontal: 'start',
        vertical: 'start',
        dynamic: false,
    },
    9: {
        column: 3,
        row: 3,
        width: 1,
        height: 1,
        horizontal: 'start',
        vertical: 'start',
        dynamic: false,
    },
    10: {
        column: 3,
        row: 6,
        width: 1,
        height: 1,
        horizontal: 'start',
        vertical: 'start',
        dynamic: false,
    },
    11: {
        column: 6,
        row: 6,
        width: 1,
        height: 1,
        horizontal: 'start',
        vertical: 'start',
        dynamic: false,
    },
};

/**
 * Creates the administrator moderation confirmation-dialog state.
 *
 * @returns {{modalOpen: boolean, targetType: string, targetIdentifier: string, action: string, reason: string, idempotencyKey: string, openModeration: Function, closeModeration: Function}}
 */
export function moderationPanel() {
    return {
        modalOpen: false,
        targetType: 'account',
        targetIdentifier: '',
        action: 'block',
        reason: '',
        idempotencyKey: '',

        /**
         * Opens a fresh, idempotent confirmation for the selected game target.
         *
         * @param {string} type The legacy target type.
         * @param {string} identifier The account ID or character name.
         * @param {string} nextAction The block or unblock operation.
         */
        openModeration(type, identifier, nextAction) {
            this.targetType = type;
            this.targetIdentifier = identifier;
            this.action = nextAction;
            this.reason = '';
            this.idempotencyKey = window.crypto.randomUUID();
            this.modalOpen = true;
            this.$nextTick(() => this.$refs.moderationReason?.focus());
        },

        /**
         * Closes the confirmation dialog without submitting a game mutation.
         */
        closeModeration() {
            this.modalOpen = false;
        },
    };
}

/**
 * Creates the bilingual news editor and recoverable-delete confirmation state.
 *
 * @param {Array<object>} posts The administrator-visible article records.
 * @param {string} createUrl The application route used to create articles.
 * @returns {object}
 */
export function contentManager(posts, createUrl) {
    return {
        posts,
        editorOpen: false,
        deleteOpen: false,
        editing: false,
        formAction: createUrl,
        deleteAction: '',
        form: {
            slug: '',
            type: 'news',
            title_en: '',
            title_bg: '',
            excerpt_en: '',
            excerpt_bg: '',
            body_en: '',
            body_bg: '',
            status: 'draft',
            published_at: '',
        },

        /**
         * Opens a blank bilingual article form.
         */
        openCreate() {
            this.editing = false;
            this.formAction = createUrl;
            this.form = {
                slug: '',
                type: 'news',
                title_en: '',
                title_bg: '',
                excerpt_en: '',
                excerpt_bg: '',
                body_en: '',
                body_bg: '',
                status: 'draft',
                published_at: '',
            };
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.newsTitle?.focus());
        },

        /**
         * Opens an existing article in the same bilingual editor.
         *
         * @param {object} post The selected administrator article payload.
         */
        openEdit(post) {
            this.editing = true;
            this.formAction = post.update_url;
            this.form = {
                slug: post.slug,
                type: post.type,
                title_en: post.title_en,
                title_bg: post.title_bg,
                excerpt_en: post.excerpt_en,
                excerpt_bg: post.excerpt_bg,
                body_en: post.body_en,
                body_bg: post.body_bg,
                status: post.status,
                published_at: post.published_at,
            };
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.newsTitle?.focus());
        },

        /**
         * Closes the article editor without persisting changes.
         */
        closeEditor() {
            this.editorOpen = false;
        },

        /**
         * Opens the soft-delete confirmation for one selected article.
         *
         * @param {object} post The article selected for recoverable deletion.
         */
        openDelete(post) {
            this.form.title_en = post.title_en;
            this.deleteAction = post.delete_url;
            this.deleteOpen = true;
        },

        /**
         * Closes the soft-delete confirmation without changing content.
         */
        closeDelete() {
            this.deleteOpen = false;
        },
    };
}

/**
 * Creates the bilingual download editor and recoverable-delete dialog state.
 *
 * @param {Array<object>} downloads The administrator-visible download records.
 * @param {string} createUrl The route used to create downloads.
 * @returns {object}
 */
export function downloadManager(downloads, createUrl) {
    /**
     * Returns a fresh download form state without sharing mutable values.
     *
     * @returns {object}
     */
    const emptyForm = () => ({
        slug: '',
        category: 'client',
        title_en: '',
        title_bg: '',
        description_en: '',
        description_bg: '',
        version: '',
        file_size: '',
        download_url: '',
        checksum_sha256: '',
        sort_order: 0,
        status: 'draft',
        published_at: '',
    });

    return {
        downloads,
        editorOpen: false,
        deleteOpen: false,
        editing: false,
        formAction: createUrl,
        deleteAction: '',
        form: emptyForm(),

        /**
         * Opens a blank bilingual download form.
         */
        openCreate() {
            this.editing = false;
            this.formAction = createUrl;
            this.form = emptyForm();
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.downloadTitle?.focus());
        },

        /**
         * Opens an existing download in the shared editor.
         *
         * @param {object} download The selected administrator download payload.
         */
        openEdit(download) {
            this.editing = true;
            this.formAction = download.update_url;
            this.form = { ...download };
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.downloadTitle?.focus());
        },

        /**
         * Closes the editor without persisting changes.
         */
        closeEditor() {
            this.editorOpen = false;
        },

        /**
         * Opens the recoverable-delete confirmation for one download.
         *
         * @param {object} download The download selected for deletion.
         */
        openDelete(download) {
            this.form.title_en = download.title_en;
            this.deleteAction = download.delete_url;
            this.deleteOpen = true;
        },

        /**
         * Closes the delete dialog without changing content.
         */
        closeDelete() {
            this.deleteOpen = false;
        },
    };
}

/**
 * Creates the public server-directory dropdown state.
 *
 * @returns {object}
 */
export function serverSwitcher() {
    return {
        open: false,

        /**
         * Opens or closes the list of related server websites.
         */
        toggle() {
            this.open = !this.open;
        },

        /**
         * Closes the destination list without navigating.
         */
        close() {
            this.open = false;
        },
    };
}

/**
 * Creates the administrator editor for public server destinations.
 *
 * @param {Array<object>} servers The administrator-visible server records.
 * @param {string} createUrl The route used to create destinations.
 * @returns {object}
 */
export function gameServerManager(servers, createUrl) {
    /**
     * Returns a fresh server form without shared mutable state.
     *
     * @returns {object}
     */
    const emptyForm = () => ({
        slug: '',
        name: '',
        version: '97d + 99i',
        experience: '9999x',
        drop: '60%',
        capacity: 500,
        show_online_status: true,
        show_online_users: true,
        is_current: false,
        is_visible: true,
        website_url: '',
        sort_order: 10,
    });

    return {
        servers,
        editorOpen: false,
        deleteOpen: false,
        editing: false,
        formAction: createUrl,
        deleteAction: '',
        form: emptyForm(),

        /**
         * Opens a blank destination editor.
         */
        openCreate() {
            this.editing = false;
            this.formAction = createUrl;
            this.form = emptyForm();
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.serverName?.focus());
        },

        /**
         * Opens an existing destination in the shared editor.
         *
         * @param {object} server The selected server-directory record.
         */
        openEdit(server) {
            this.editing = true;
            this.formAction = server.update_url;
            this.form = { ...server };
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.serverName?.focus());
        },

        /**
         * Closes the destination editor without saving.
         */
        closeEditor() {
            this.editorOpen = false;
        },

        /**
         * Opens deletion confirmation for an external destination.
         *
         * @param {object} server The external destination selected for deletion.
         */
        openDelete(server) {
            this.form.name = server.name;
            this.deleteAction = server.delete_url;
            this.deleteOpen = true;
        },

        /**
         * Closes deletion confirmation without changing the directory.
         */
        closeDelete() {
            this.deleteOpen = false;
        },
    };
}

/**
 * Creates the wallet adjustment preview and confirmation state.
 *
 * @param {number} initialBalance The authoritative balance rendered by Laravel.
 * @returns {object}
 */
export function walletManager(initialBalance) {
    return {
        initialBalance,
        direction: 'credit',
        amount: 1,
        confirmationOpen: false,

        /**
         * Calculates the client-side preview without replacing server validation.
         *
         * @returns {number}
         */
        get previewBalance() {
            const normalizedAmount = Number.isFinite(Number(this.amount))
                ? Math.max(0, Number(this.amount))
                : 0;

            return this.direction === 'credit'
                ? this.initialBalance + normalizedAmount
                : this.initialBalance - normalizedAmount;
        },

        /**
         * Opens confirmation only after native form constraints pass.
         */
        openConfirmation() {
            if (!this.$refs.walletForm.reportValidity()) {
                return;
            }

            this.confirmationOpen = true;
        },

        /**
         * Closes confirmation without submitting a wallet write.
         */
        closeConfirmation() {
            this.confirmationOpen = false;
        },

        /**
         * Submits the unchanged form and shared idempotency key exactly once per click.
         */
        submitAdjustment() {
            this.$refs.walletForm.submit();
        },
    };
}

/**
 * Creates the modal state for one idempotent character VIP purchase.
 *
 * @param {Array<object>} plans Localized VIP plans available to the player.
 * @param {string} initialPlanId The plan restored from old input or the first offer.
 * @param {string} initialCharacter The character restored from old input or account order.
 * @param {boolean} initiallyOpen Whether validation feedback should reopen the modal.
 * @returns {object} Alpine state for plan selection and purchase confirmation.
 */
export function vipPurchaseManager(
    plans,
    initialPlanId,
    initialCharacter,
    initiallyOpen,
) {
    return {
        vipOpen: initiallyOpen,
        confirmationOpen: false,
        plans,
        selectedPlanId: initialPlanId,
        selectedCharacter: initialCharacter,

        /**
         * Returns the currently selected localized plan without callback functions.
         *
         * @returns {object|null}
         */
        get selectedPlan() {
            for (const plan of this.plans) {
                if (String(plan.id) === String(this.selectedPlanId)) {
                    return plan;
                }
            }

            return null;
        },
    };
}

/**
 * Creates the full-administrator Game Master identity editor state.
 *
 * @param {string} createUrl The route used to create a Game Master.
 * @returns {object}
 */
export function gameMasterManager(createUrl) {
    return {
        editorOpen: false,
        editing: false,
        formAction: createUrl,
        form: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            is_active: true,
            permissions: [],
        },

        /**
         * Opens a blank Game Master form with no implicit permissions.
         */
        openCreate() {
            this.editing = false;
            this.formAction = createUrl;
            this.form = {
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
                is_active: true,
                permissions: [],
            };
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.gameMasterName?.focus());
        },

        /**
         * Opens one existing Game Master without ever loading a password value.
         *
         * @param {object} gameMaster The password-free identity payload.
         */
        openEdit(gameMaster) {
            this.editing = true;
            this.formAction = gameMaster.update_url;
            this.form = {
                name: gameMaster.name,
                email: gameMaster.email,
                password: '',
                password_confirmation: '',
                is_active: gameMaster.is_active,
                permissions: [...gameMaster.permissions],
            };
            this.editorOpen = true;
            this.$nextTick(() => this.$refs.gameMasterName?.focus());
        },

        /**
         * Closes the identity editor and discards unsaved password input.
         */
        closeEditor() {
            this.form.password = '';
            this.form.password_confirmation = '';
            this.editorOpen = false;
        },
    };
}

/**
 * Creates the responsive navigation state shared by all application areas.
 *
 * @returns {object}
 */
export function applicationNavigation() {
    return {
        mobileMenuOpen: false,
        cookieNoticeVisible: false,

        /**
         * Restores whether the visitor has already acknowledged the cookie notice.
         */
        init() {
            try {
                this.cookieNoticeVisible = window.localStorage.getItem('mu_cookie_notice_v1') !== 'acknowledged';
            } catch {
                this.cookieNoticeVisible = true;
            }
        },

        /**
         * Opens or closes the mobile navigation drawer.
         */
        toggleMobileMenu() {
            this.mobileMenuOpen = !this.mobileMenuOpen;
        },

        /**
         * Closes the mobile navigation drawer after a navigation choice.
         */
        closeMobileMenu() {
            this.mobileMenuOpen = false;
        },

        /**
         * Stores the informational cookie notice choice in browser-local storage.
         */
        acknowledgeCookieNotice() {
            try {
                window.localStorage.setItem('mu_cookie_notice_v1', 'acknowledged');
            } catch {
                // The notice can still close for this page when storage is unavailable.
            }

            this.cookieNoticeVisible = false;
        },
    };
}

/**
 * Creates the public ranking board tab state.
 *
 * @param {string} initialTab The ranking category selected on first render.
 * @returns {{activeTab: string, selectTab: Function}}
 */
export function rankingBoard(initialTab = 'players') {
    return {
        activeTab: initialTab,

        /**
         * Selects the ranking category displayed in the shared results panel.
         *
         * @param {string} tab The category key selected by the visitor.
         */
        selectTab(tab) {
            this.activeTab = tab;
        },
    };
}

/**
 * Creates an asynchronous, URL-synchronized ranking explorer.
 *
 * @param {object} initialPage The first server-rendered ranking page.
 * @param {string} dataUrl The endpoint used for filtered ranking pages.
 * @returns {object}
 */
export function rankingExplorer(initialPage, dataUrl) {
    return {
        board: initialPage.board,
        classFilter: initialPage.class_filter,
        search: initialPage.search,
        onlineOnly: initialPage.online_only,
        pageData: initialPage,
        loading: false,
        failed: false,
        searchTimer: null,
        requestController: null,
        popstateHandler: null,

        /**
         * Starts browser-history synchronization for shareable filters.
         *
         * @returns {void}
         */
        init() {
            this.popstateHandler = this.restoreFromLocation.bind(this);
            window.addEventListener('popstate', this.popstateHandler);
        },

        /**
         * Releases timers, requests and global event listeners.
         *
         * @returns {void}
         */
        destroy() {
            window.clearTimeout(this.searchTimer);
            this.requestController?.abort();

            if (this.popstateHandler !== null) {
                window.removeEventListener('popstate', this.popstateHandler);
            }
        },

        /**
         * Selects an independently ordered leaderboard.
         *
         * @param {string} selectedBoard The overall, PK or guild board key.
         * @returns {Promise<void>}
         */
        async selectBoard(selectedBoard) {
            this.board = selectedBoard;

            if (selectedBoard === 'guilds') {
                this.classFilter = '';
                this.onlineOnly = false;
            }

            await this.loadPage(1);
        },

        /**
         * Selects one exact 97d+99i character class.
         *
         * @param {string} selectedClass The class filter key or an empty value.
         * @returns {Promise<void>}
         */
        async selectClass(selectedClass) {
            this.classFilter = selectedClass;
            await this.loadPage(1);
        },

        /**
         * Reloads the first page after the online filter changes.
         *
         * @returns {Promise<void>}
         */
        async toggleOnline() {
            await this.loadPage(1);
        },

        /**
         * Debounces search requests while the visitor types.
         *
         * @returns {void}
         */
        scheduleSearch() {
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(
                () => this.loadPage(1),
                300,
            );
        },

        /**
         * Requests one exact server-side ranking page and updates the URL.
         *
         * @param {number} page The one-based page number to request.
         * @param {boolean} updateLocation Whether to update browser history.
         * @returns {Promise<void>}
         */
        async loadPage(page, updateLocation = true) {
            this.requestController?.abort();
            this.requestController = new AbortController();
            const activeController = this.requestController;
            this.loading = true;
            this.failed = false;

            const parameters = this.queryParameters(page);

            try {
                const response = await window.fetch(`${dataUrl}?${parameters.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: activeController.signal,
                });

                if (!response.ok) {
                    throw new Error(`Ranking request failed with ${response.status}.`);
                }

                this.pageData = await response.json();

                if (updateLocation) {
                    window.history.pushState({}, '', `${window.location.pathname}?${parameters.toString()}`);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.failed = true;
                }
            } finally {
                if (this.requestController === activeController) {
                    this.loading = false;
                }
            }
        },

        /**
         * Creates the canonical query string for one explorer state.
         *
         * @param {number} page The one-based page number.
         * @returns {URLSearchParams}
         */
        queryParameters(page) {
            const parameters = new URLSearchParams({
                board: this.board,
                page: String(page),
                per_page: String(this.pageData.meta.per_page),
            });

            if (this.board !== 'guilds' && this.classFilter !== '') {
                parameters.set('class', this.classFilter);
            }

            if (this.search.trim() !== '') {
                parameters.set('search', this.search.trim());
            }

            if (this.board !== 'guilds' && this.onlineOnly) {
                parameters.set('online', '1');
            }

            return parameters;
        },

        /**
         * Restores explorer controls when browser history changes.
         *
         * @returns {Promise<void>}
         */
        async restoreFromLocation() {
            const parameters = new URLSearchParams(window.location.search);

            this.board = parameters.get('board') ?? 'overall';
            this.classFilter = parameters.get('class') ?? '';
            this.search = parameters.get('search') ?? '';
            this.onlineOnly = parameters.get('online') === '1';

            await this.loadPage(Number(parameters.get('page') ?? 1), false);
        },

        /**
         * Returns the first three entries only on the first unsearched page.
         *
         * @returns {Array<object>}
         */
        get podiumItems() {
            if (this.pageData.meta.current_page !== 1 || this.search.trim() !== '') {
                return [];
            }

            return this.pageData.items.slice(0, 3);
        },

        /**
         * Returns entries not already represented on the podium.
         *
         * @returns {Array<object>}
         */
        get listItems() {
            return this.pageData.items.slice(this.podiumItems.length);
        },

        /**
         * Returns a compact window of selectable page numbers.
         *
         * @returns {Array<number>}
         */
        get pageNumbers() {
            const lastPage = this.pageData.meta.last_page;
            const currentPage = this.pageData.meta.current_page;
            const firstPage = Math.max(1, Math.min(currentPage - 2, lastPage - 4));
            const finalPage = Math.min(lastPage, firstPage + 4);
            const pages = [];

            for (let page = firstPage; page <= finalPage; page += 1) {
                pages.push(page);
            }

            return pages;
        },
    };
}

/**
 * Creates server-synchronized countdowns for recurring realm events.
 *
 * @param {Array<object>} initialEvents Server-calculated occurrences for each event.
 * @param {string} serverNow ISO timestamp captured while rendering the page.
 * @returns {object} Alpine state for countdowns and the full schedule disclosure.
 */
export function eventSchedule(initialEvents, serverNow) {
    const normalizedEvents = [];

    for (const initialEvent of initialEvents) {
        const occurrenceTimestamps = [];

        for (const occurrence of initialEvent.occurrences) {
            occurrenceTimestamps.push(Date.parse(occurrence));
        }

        normalizedEvents.push({
            ...initialEvent,
            previousOccurrenceTimestamp: Date.parse(initialEvent.previousOccurrence),
            occurrenceTimestamps,
        });
    }

    return {
        events: normalizedEvents,
        clockOffsetMilliseconds: Date.parse(serverNow) - Date.now(),
        nowTimestamp: Date.parse(serverNow),
        scheduleOpen: false,
        timerId: null,

        /**
         * Starts one shared timer for every event card.
         */
        init() {
            this.tick();
            this.timerId = window.setInterval(
                this.tick.bind(this),
                1000,
            );
        },

        /**
         * Stops the shared timer when Alpine removes the event section.
         */
        destroy() {
            if (this.timerId !== null) {
                window.clearInterval(this.timerId);
            }
        },

        /**
         * Synchronizes the local display with server time and advances expired occurrences.
         */
        tick() {
            this.nowTimestamp = Date.now() + this.clockOffsetMilliseconds;

            for (const event of this.events) {
                this.advanceExpiredOccurrences(event);
            }
        },

        /**
         * Moves an event to its next configured occurrence after a countdown reaches zero.
         *
         * @param {object} event The normalized recurring event being advanced.
         */
        advanceExpiredOccurrences(event) {
            while (
                event.occurrenceTimestamps.length > 1
                && event.occurrenceTimestamps[0] <= this.nowTimestamp
            ) {
                event.previousOccurrenceTimestamp = event.occurrenceTimestamps.shift();
            }
        },

        /**
         * Calculates whole seconds remaining until an event's next occurrence.
         *
         * @param {object} event The normalized event displayed by one card.
         * @returns {number}
         */
        remainingSeconds(event) {
            const nextOccurrence = event.occurrenceTimestamps[0];

            if (nextOccurrence === undefined) {
                return 0;
            }

            return Math.max(0, Math.ceil((nextOccurrence - this.nowTimestamp) / 1000));
        },

        /**
         * Formats a countdown as MM:SS or HH:MM:SS when at least one hour remains.
         *
         * @param {object} event The normalized event displayed by one card.
         * @returns {string}
         */
        formattedCountdown(event) {
            const totalSeconds = this.remainingSeconds(event);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            const paddedMinutes = String(minutes).padStart(2, '0');
            const paddedSeconds = String(seconds).padStart(2, '0');

            if (hours === 0) {
                return `${paddedMinutes}:${paddedSeconds}`;
            }

            return `${String(hours).padStart(2, '0')}:${paddedMinutes}:${paddedSeconds}`;
        },

        /**
         * Calculates elapsed progress between the previous and next occurrence.
         *
         * @param {object} event The normalized event displayed by one card.
         * @returns {number}
         */
        progressPercent(event) {
            const nextOccurrence = event.occurrenceTimestamps[0];

            if (nextOccurrence === undefined) {
                return 100;
            }

            const cycleDuration = nextOccurrence - event.previousOccurrenceTimestamp;
            const elapsedDuration = this.nowTimestamp - event.previousOccurrenceTimestamp;

            if (cycleDuration <= 0) {
                return 100;
            }

            return Math.max(0, Math.min(100, (elapsedDuration / cycleDuration) * 100));
        },

        /**
         * Opens or closes the complete daily event schedule.
         */
        toggleSchedule() {
            this.scheduleOpen = !this.scheduleOpen;
        },

        /**
         * Closes the complete schedule from the Escape key.
         */
        closeSchedule() {
            this.scheduleOpen = false;
        },
    };
}

/**
 * Creates a zero-value allocation map for one character's version-defined stats.
 *
 * @param {object|null} character The selected account character.
 * @returns {Record<string, number>}
 */
function createEmptyStatAllocations(character) {
    const allocations = {};

    for (const statDefinition of character?.statDefinitions ?? []) {
        allocations[statDefinition.key] = 0;
    }

    return allocations;
}

/**
 * Creates shared decoded-item labels and tooltip formatters for player-facing views.
 *
 * @param {object} itemOptionLabels Locale-aware item option labels.
 * @param {object|null} item The optional item displayed by a standalone inspector.
 * @returns {object} Alpine state with one optional item and shared presentation methods.
 */
export function itemInspector(itemOptionLabels, item = null) {
    return {
        itemOptionLabels,
        item,

        /**
         * Builds the item title with its level and excellent classification.
         *
         * @param {object} inspectedItem A decoded 97d+99i item.
         * @returns {string}
         */
        itemTitle(inspectedItem) {
            const level = inspectedItem.level > 0 ? ` +${inspectedItem.level}` : '';
            const prefix = inspectedItem.excellentMask > 0 && inspectedItem.excellentType !== 4
                ? `${this.itemOptionLabels.excellentPrefix} `
                : '';

            return `${prefix}${inspectedItem.name}${level}`;
        },

        /**
         * Returns the visual name tone for wings and option-bearing items.
         *
         * @param {object} inspectedItem A decoded game item.
         * @returns {string}
         */
        itemNameTone(inspectedItem) {
            if (inspectedItem?.excellentType === 4) {
                return 'wing';
            }

            if (inspectedItem?.excellentMask > 0) {
                return 'excellent';
            }

            return inspectedItem?.rarity ?? 'normal';
        },

        /**
         * Formats the current durability value for an item tooltip.
         *
         * @param {object} inspectedItem A decoded 97d+99i item.
         * @returns {string}
         */
        itemDurabilityText(inspectedItem) {
            return `${this.itemOptionLabels.durability}: ${inspectedItem.durability}`;
        },

        /**
         * Formats the version-specific additional option encoded on an item.
         *
         * @param {object} inspectedItem A decoded 97d+99i item.
         * @returns {string|null}
         */
        itemAdditionalOption(inspectedItem) {
            if (inspectedItem.optionLevel <= 0) {
                return null;
            }

            if (inspectedItem.optionType === 1) {
                return `${this.itemOptionLabels.automaticHpRecovery} +${inspectedItem.optionLevel}%`;
            }

            if (inspectedItem.optionType === 2) {
                return `${this.itemOptionLabels.additionalDefenseRate} +${inspectedItem.optionLevel * 5}`;
            }

            const label = inspectedItem.excellentType === 1
                ? this.itemOptionLabels.additionalDefense
                : this.itemOptionLabels.additionalDamage;

            return `${label} +${inspectedItem.optionLevel * 4}`;
        },

        /**
         * Returns every excellent option enabled by the item's six-bit mask.
         *
         * @param {object} inspectedItem A decoded 97d+99i item.
         * @returns {Array<string>}
         */
        itemExcellentOptions(inspectedItem) {
            if (inspectedItem.excellentMask <= 0) {
                return [];
            }

            const optionGroups = this.itemOptionLabels.excellent;
            let optionGroup = optionGroups.weapon;

            if (inspectedItem.excellentType === 4) {
                optionGroup = optionGroups.wing;
            } else if (inspectedItem.excellentType === 1) {
                optionGroup = optionGroups.armor;
            }

            const enabledOptions = [];

            for (let optionIndex = 0; optionIndex < 6; optionIndex += 1) {
                const optionEnabled = (inspectedItem.excellentMask & (1 << optionIndex)) !== 0;
                const optionLabel = optionGroup[optionIndex] ?? '';

                if (optionEnabled && optionLabel !== '') {
                    enabledOptions.push(optionLabel);
                }
            }

            return enabledOptions;
        },

        /**
         * Formats the retained ancient option byte without guessing a set name.
         *
         * @param {object} inspectedItem A decoded 97d+99i item.
         * @returns {string}
         */
        itemAncientOption(inspectedItem) {
            return `${this.itemOptionLabels.ancientOption}: ${inspectedItem.ancientOption}`;
        },
    };
}

/**
 * Creates the interactive character, stat allocation and account tab state.
 *
 * @param {Array<object>} characters Character records available to the account.
 * @param {object|null} resetPreviewConfiguration Reset preview endpoint and localized labels.
 * @returns {object} Alpine state for the account character panel.
 */
export function accountPanel(characters, resetPreviewConfiguration = null) {
    const requestedCharacter = resetPreviewConfiguration?.initialCharacter ?? null;
    const requestedCharacterIndex = characters.findIndex(
        (character) => character.name === requestedCharacter,
    );
    const initialCharacterIndex = Math.max(0, requestedCharacterIndex);

    return {
        ...itemInspector(resetPreviewConfiguration?.itemOptionLabels ?? {}),
        characters,
        resetPreviewConfiguration,
        selectedCharacterIndex: initialCharacterIndex,
        activeSection: resetPreviewConfiguration?.initialSection ?? 'overview',
        resetPreview: null,
        resetPreviewLoading: false,
        resetPreviewError: null,
        resetPreviewRequestId: 0,
        resetConfirmationOpen: false,
        resetExecuting: false,
        resetSuccessMessage: null,
        grandResetPreview: null,
        grandResetPreviewLoading: false,
        grandResetPreviewError: null,
        grandResetPreviewRequestId: 0,
        grandResetConfirmationOpen: false,
        grandResetExecuting: false,
        grandResetSuccessMessage: null,
        statResetPreview: null,
        statResetPreviewLoading: false,
        statResetPreviewError: null,
        statResetPreviewRequestId: 0,
        statResetConfirmationOpen: false,
        statResetExecuting: false,
        statResetSuccessMessage: null,
        pkClearPreview: null,
        pkClearPreviewLoading: false,
        pkClearPreviewError: null,
        pkClearPreviewRequestId: 0,
        pkClearConfirmationOpen: false,
        pkClearExecuting: false,
        pkClearSuccessMessage: null,
        moveToTownPreview: null,
        moveToTownPreviewLoading: false,
        moveToTownPreviewError: null,
        moveToTownPreviewRequestId: 0,
        moveToTownConfirmationOpen: false,
        moveToTownExecuting: false,
        moveToTownSuccessMessage: null,
        statPreview: null,
        statPreviewLoading: false,
        statPreviewError: null,
        statPreviewRequestId: 0,
        statConfirmationOpen: false,
        statExecuting: false,
        statSuccessMessage: null,
        itemStorage: null,
        itemStorageLoading: false,
        itemStorageError: null,
        itemStorageRequestId: 0,
        selectedVaultNumber: null,
        itemActionModalOpen: false,
        selectedActionItem: null,
        selectedWebStorageItem: null,
        selectedItemLocation: null,
        itemActionIdempotencyKey: null,
        itemSaleType: 'fixed',
        allocations: createEmptyStatAllocations(characters[initialCharacterIndex] ?? null),

        /**
         * Restores the URL-selected tab through the same loader used by tab clicks.
         */
        init() {
            this.selectSection(this.activeSection);
        },

        /**
         * Stores the selected character and account tab in the current URL.
         */
        synchronizeAccountLocation() {
            const location = new URL(window.location.href);

            if (this.selectedCharacter === undefined) {
                location.searchParams.delete('character');
            } else {
                location.searchParams.set('character', this.selectedCharacter.name);
            }

            location.searchParams.set('tab', this.activeSection);
            window.history.replaceState({}, '', location);
        },

        /**
         * Returns the currently selected character record.
         *
         * @returns {object}
         */
        get selectedCharacter() {
            return this.characters[this.selectedCharacterIndex];
        },

        /**
         * Calculates the number of unallocated points remaining in the preview.
         *
         * @returns {number}
         */
        get remainingPoints() {
            return this.selectedCharacter.points - this.allocatedPoints;
        },

        /**
         * Calculates the complete number of points in the local allocation draft.
         *
         * @returns {number}
         */
        get allocatedPoints() {
            let allocatedPoints = 0;

            for (const value of Object.values(this.allocations)) {
                allocatedPoints += value;
            }

            return allocatedPoints;
        },

        /**
         * Applies a server-returned dynamic stat snapshot to the selected character.
         *
         * @param {Record<string, number>} stats The authoritative stat values.
         */
        applyCharacterStats(stats) {
            this.selectedCharacter.stats = {...stats};

            for (const [statKey, value] of Object.entries(stats)) {
                this.selectedCharacter[statKey] = value;
            }
        },

        /**
         * Returns the currently selected decoded warehouse vault.
         *
         * @returns {object|null}
         */
        get selectedWarehouseVault() {
            if (this.itemStorage === null) {
                return null;
            }

            return this.itemStorage.warehouse.vaults.find(
                (vault) => vault.number === this.selectedVaultNumber,
            ) ?? null;
        },

        /**
         * Returns the localized inventory outcome from the server preview.
         *
         * @returns {string}
         */
        get resetInventoryPolicyLabel() {
            if (this.resetPreview === null) {
                return '';
            }

            const labels = this.resetPreviewConfiguration.inventoryPolicies;

            return labels[this.resetPreview.inventoryPolicy] ?? this.resetPreview.inventoryPolicy;
        },

        /**
         * Returns localized explanations for every failed reset requirement.
         *
         * @returns {Array<string>}
         */
        get resetBlockMessages() {
            if (this.resetPreview === null) {
                return [];
            }

            const reasonLabels = this.resetPreviewConfiguration.blockReasons;
            const messages = [];

            for (const reason of this.resetPreview.blockReasons) {
                messages.push(reasonLabels[reason] ?? reason);
            }

            return messages;
        },

        /**
         * Returns the localized inventory outcome from the grand-reset preview.
         *
         * @returns {string}
         */
        get grandResetInventoryPolicyLabel() {
            if (this.grandResetPreview === null) {
                return '';
            }

            const labels = this.resetPreviewConfiguration.inventoryPolicies;

            return labels[this.grandResetPreview.inventoryPolicy]
                ?? this.grandResetPreview.inventoryPolicy;
        },

        /**
         * Returns localized explanations for every failed grand-reset requirement.
         *
         * @returns {Array<string>}
         */
        get grandResetBlockMessages() {
            if (this.grandResetPreview === null) {
                return [];
            }

            const reasonLabels = this.resetPreviewConfiguration.grandResetBlockReasons;
            const messages = [];

            for (const reason of this.grandResetPreview.blockReasons) {
                messages.push(reasonLabels[reason] ?? reason);
            }

            return messages;
        },

        /**
         * Returns localized explanations for every failed stat-reset requirement.
         *
         * @returns {Array<string>}
         */
        get statResetBlockMessages() {
            if (this.statResetPreview === null) {
                return [];
            }

            const reasonLabels = this.resetPreviewConfiguration.statResetBlockReasons;
            const messages = [];

            for (const reason of this.statResetPreview.blockReasons) {
                messages.push(reasonLabels[reason] ?? reason);
            }

            return messages;
        },

        /**
         * Returns localized explanations for every failed PK-clear requirement.
         *
         * @returns {Array<string>}
         */
        get pkClearBlockMessages() {
            if (this.pkClearPreview === null) {
                return [];
            }

            const reasonLabels = this.resetPreviewConfiguration.pkClearBlockReasons;
            const messages = [];

            for (const reason of this.pkClearPreview.blockReasons) {
                messages.push(reasonLabels[reason] ?? reason);
            }

            return messages;
        },

        /**
         * Returns the localized PK-clear price formula from the active server profile.
         *
         * @returns {string}
         */
        get pkClearPriceModeLabel() {
            if (this.pkClearPreview === null) {
                return '';
            }

            const labels = this.resetPreviewConfiguration.pkClearPriceModes;

            return labels[this.pkClearPreview.priceMode] ?? this.pkClearPreview.priceMode;
        },

        /**
         * Returns localized explanations for every failed Move to Town requirement.
         *
         * @returns {Array<string>}
         */
        get moveToTownBlockMessages() {
            if (this.moveToTownPreview === null) {
                return [];
            }

            const labels = this.resetPreviewConfiguration.moveToTownBlockReasons;
            const messages = [];

            for (const reason of this.moveToTownPreview.blockReasons) {
                messages.push(labels[reason] ?? reason);
            }

            return messages;
        },

        /**
         * Returns localized explanations for every failed allocation requirement.
         *
         * @returns {Array<string>}
         */
        get statBlockMessages() {
            if (this.statPreview === null) {
                return [];
            }

            const reasonLabels = this.resetPreviewConfiguration.statBlockReasons;
            const messages = [];

            for (const reason of this.statPreview.blockReasons) {
                messages.push(reasonLabels[reason] ?? reason);
            }

            return messages;
        },

        /**
         * Selects a character and clears any pending stat allocation preview.
         *
         * @param {number} characterIndex The character index selected by the player.
         */
        selectCharacter(characterIndex) {
            this.selectedCharacterIndex = characterIndex;

            if (this.activeSection === 'vaults') {
                this.activeSection = 'overview';
            }

            this.itemStorageRequestId += 1;
            this.itemStorage = null;
            this.itemStorageError = null;
            this.itemStorageLoading = false;
            this.selectedVaultNumber = null;
            this.resetAllocations();
            this.resetPreview = null;
            this.resetPreviewError = null;
            this.resetConfirmationOpen = false;
            this.resetSuccessMessage = null;
            this.grandResetPreview = null;
            this.grandResetPreviewError = null;
            this.grandResetConfirmationOpen = false;
            this.grandResetSuccessMessage = null;
            this.statResetPreview = null;
            this.statResetPreviewError = null;
            this.statResetConfirmationOpen = false;
            this.statResetSuccessMessage = null;
            this.pkClearPreview = null;
            this.pkClearPreviewError = null;
            this.pkClearConfirmationOpen = false;
            this.pkClearSuccessMessage = null;
            this.moveToTownPreview = null;
            this.moveToTownPreviewError = null;
            this.moveToTownConfirmationOpen = false;
            this.moveToTownSuccessMessage = null;
            this.statSuccessMessage = null;
            this.synchronizeAccountLocation();

            if (this.activeSection === 'reset') {
                void this.loadResetPreview();
            }

            if (this.activeSection === 'grand-reset') {
                void this.loadGrandResetPreview();
            }

            if (this.activeSection === 'stat-reset') {
                void this.loadStatResetPreview();
            }

            if (this.activeSection === 'pk-clear') {
                void this.loadPkClearPreview();
            }

            if (this.activeSection === 'move-to-town') {
                void this.loadMoveToTownPreview();
            }

            if (this.activeSection === 'inventory') {
                void this.loadItemStorage();
            }
        },

        /**
         * Selects the active character management section.
         *
         * @param {string} section The account section to display.
         */
        selectSection(section) {
            this.activeSection = section;
            this.synchronizeAccountLocation();

            if (section === 'reset') {
                void this.loadResetPreview();
            }

            if (section === 'grand-reset') {
                void this.loadGrandResetPreview();
            }

            if (section === 'stat-reset') {
                void this.loadStatResetPreview();
            }

            if (section === 'pk-clear') {
                void this.loadPkClearPreview();
            }

            if (section === 'move-to-town') {
                void this.loadMoveToTownPreview();
            }

            if (section === 'inventory') {
                void this.loadItemStorage();
            }

            if (section === 'vaults') {
                void this.loadItemStorage();
            }
        },

        /**
         * Opens account-scoped game and web vault management.
         */
        selectAccountVaults() {
            this.selectSection('vaults');
        },

        /**
         * Loads ownership-scoped equipment, inventory and warehouse data on demand.
         */
        async loadItemStorage() {
            if (this.resetPreviewConfiguration === null) {
                return;
            }

            const requestId = this.itemStorageRequestId + 1;

            this.itemStorageRequestId = requestId;
            this.itemStorageLoading = true;
            this.itemStorageError = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.itemStorageUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter?.name ?? null,
                        }),
                    },
                );

                if (!response.ok) {
                    throw new Error(`Item storage request failed with status ${response.status}.`);
                }

                const responsePayload = await response.json();

                if (requestId !== this.itemStorageRequestId) {
                    return;
                }

                this.itemStorage = responsePayload.storage;

                const activeVault = this.itemStorage.warehouse.vaults.find(
                    (vault) => vault.active,
                );
                const firstVault = this.itemStorage.warehouse.vaults[0] ?? null;

                this.selectedVaultNumber = activeVault?.number ?? firstVault?.number ?? null;
            } catch {
                if (requestId === this.itemStorageRequestId) {
                    this.itemStorageError = this.resetPreviewConfiguration.itemStorageFailed;
                }
            } finally {
                if (requestId === this.itemStorageRequestId) {
                    this.itemStorageLoading = false;
                }
            }
        },

        /**
         * Selects one already-loaded MultiVault warehouse without another request.
         *
         * @param {number} vaultNumber The numbered vault selected by the player.
         */
        selectWarehouseVault(vaultNumber) {
            this.selectedVaultNumber = vaultNumber;
        },

        /**
         * Opens exact-item actions for one game warehouse item.
         *
         * @param {object} item The decoded warehouse item selected by the player.
         */
        openWarehouseItemActions(item) {
            this.selectedActionItem = item;
            this.selectedWebStorageItem = null;
            this.selectedItemLocation = 'warehouse';
            this.itemActionIdempotencyKey = window.crypto.randomUUID();
            this.itemSaleType = 'fixed';
            this.itemActionModalOpen = true;
        },

        /**
         * Opens the return action for one exact item held in web storage.
         *
         * @param {object} storedItem The account-scoped web storage record.
         */
        openWebStorageItemActions(storedItem) {
            this.selectedActionItem = storedItem.item;
            this.selectedWebStorageItem = storedItem;
            this.selectedItemLocation = 'web';
            this.itemActionIdempotencyKey = null;
            this.itemActionModalOpen = true;
        },

        /**
         * Closes the item action dialog and clears its exact-item selection.
         */
        closeItemActions() {
            this.itemActionModalOpen = false;
            this.selectedActionItem = null;
            this.selectedWebStorageItem = null;
            this.selectedItemLocation = null;
        },

        /**
         * Places an item over every inventory cell reserved by its catalog dimensions.
         *
         * @param {object} item A decoded item with its starting slot and dimensions.
         * @param {number} columns The number of columns in the target storage grid.
         * @param {boolean} useItemSize Whether catalog dimensions should span multiple cells.
         * @returns {object} Inline positioning properties for the item overlay.
         */
        itemGridStyle(item, columns, useItemSize = true) {
            const column = item.slot % columns;
            const row = Math.floor(item.slot / columns);
            const declaredWidth = useItemSize ? Math.max(1, item.width) : 1;
            const declaredHeight = useItemSize ? Math.max(1, item.height) : 1;
            const visibleWidth = Math.min(declaredWidth, columns - column);

            return {
                left: `calc(${column} * var(--inventory-cell-size))`,
                top: `calc(${row} * var(--inventory-cell-size))`,
                width: `calc(${visibleWidth} * var(--inventory-cell-size))`,
                height: `calc(${declaredHeight} * var(--inventory-cell-size))`,
            };
        },

        /**
         * Finds an equipped item by its verified legacy equipment slot.
         *
         * @param {Array<object>} items Decoded items in the equipment boundary.
         * @param {number} slot The legacy equipment slot number.
         * @returns {object|null}
         */
        itemFor(items, slot) {
            return items.find((item) => item.slot === slot) ?? null;
        },

        /**
         * Sizes and aligns one equipment slot from its catalog dimensions.
         *
         * @param {number} slot The legacy equipment slot number.
         * @param {object|null} item The equipped item, or null for an empty placeholder.
         * @returns {object} CSS grid placement properties.
         */
        equipmentSlotStyle(slot, item = null) {
            const placement = equipmentSlotLayout[slot];

            if (placement === undefined) {
                return {};
            }

            let width = placement.width;
            let height = placement.height;

            if (placement.dynamic && item !== null) {
                width = Math.max(1, Math.min(placement.width, item.width));
                height = Math.max(1, Math.min(placement.height, item.height));
            }

            let column = placement.column;
            let row = placement.row;

            if (placement.horizontal === 'end') {
                column += placement.width - width;
            }

            if (placement.vertical === 'end') {
                row += placement.height - height;
            }

            return {
                gridColumn: `${column} / span ${width}`,
                gridRow: `${row} / span ${height}`,
            };
        },

        /**
         * Loads a fresh ownership-scoped preview from Laravel for the selected character.
         */
        async loadResetPreview() {
            if (this.resetPreviewConfiguration === null) {
                return;
            }

            const requestId = this.resetPreviewRequestId + 1;

            this.resetPreviewRequestId = requestId;
            this.resetPreviewLoading = true;
            this.resetPreviewError = null;

            try {
                const response = await window.fetch(this.resetPreviewConfiguration.previewUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                    },
                    body: JSON.stringify({
                        characterName: this.selectedCharacter.name,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`Reset preview request failed with status ${response.status}.`);
                }

                const responsePayload = await response.json();

                if (requestId !== this.resetPreviewRequestId) {
                    return;
                }

                this.resetPreview = responsePayload.preview;
            } catch {
                if (requestId === this.resetPreviewRequestId) {
                    this.resetPreviewError = this.resetPreviewConfiguration.previewFailed;
                }
            } finally {
                if (requestId === this.resetPreviewRequestId) {
                    this.resetPreviewLoading = false;
                }
            }
        },

        /**
         * Shows the final reset confirmation only for an eligible writable preview.
         */
        openResetConfirmation() {
            if (
                this.resetPreview === null
                || !this.resetPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.resetConfirmationOpen = true;
            this.resetPreviewError = null;
        },

        /**
         * Closes the reset confirmation while no request is running.
         */
        closeResetConfirmation() {
            if (this.resetExecuting) {
                return;
            }

            this.resetConfirmationOpen = false;
        },

        /**
         * Executes the confirmed reset with a unique at-most-once operation key.
         */
        async executeReset() {
            if (
                this.resetExecuting
                || this.resetPreview === null
                || !this.resetPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.resetExecuting = true;
            this.resetPreviewError = null;
            this.resetSuccessMessage = null;

            try {
                const response = await window.fetch(this.resetPreviewConfiguration.executeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                    },
                    body: JSON.stringify({
                        characterName: this.selectedCharacter.name,
                        idempotencyKey: this.createIdempotencyKey(),
                    }),
                });
                const responsePayload = await response.json();

                if (!response.ok) {
                    const reasonMessages = this.resetPreviewConfiguration.executionReasons;

                    this.resetPreviewError = reasonMessages[responsePayload.reason]
                        ?? responsePayload.message
                        ?? this.resetPreviewConfiguration.executionFailed;

                    return;
                }

                const result = responsePayload.result;

                this.selectedCharacter.level = result.resultingLevel;
                this.selectedCharacter.resets = result.resultingResets;
                this.selectedCharacter.zen = result.resultingZen;
                this.selectedCharacter.points = result.resultingPoints;
                this.applyCharacterStats(result.resultingStats);
                this.resetConfirmationOpen = false;
                this.resetSuccessMessage = responsePayload.message
                    ?? this.resetPreviewConfiguration.completed;

                await this.loadResetPreview();
            } catch {
                this.resetPreviewError = this.resetPreviewConfiguration.executionFailed;
            } finally {
                this.resetExecuting = false;
            }
        },

        /**
         * Loads a fresh ownership-scoped grand-reset preview for the selected character.
         */
        async loadGrandResetPreview() {
            if (this.resetPreviewConfiguration === null) {
                return;
            }

            const requestId = this.grandResetPreviewRequestId + 1;

            this.grandResetPreviewRequestId = requestId;
            this.grandResetPreviewLoading = true;
            this.grandResetPreviewError = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.grandResetPreviewUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                        }),
                    },
                );

                if (!response.ok) {
                    throw new Error(
                        `Grand-reset preview request failed with status ${response.status}.`,
                    );
                }

                const responsePayload = await response.json();

                if (requestId !== this.grandResetPreviewRequestId) {
                    return;
                }

                this.grandResetPreview = responsePayload.preview;
            } catch {
                if (requestId === this.grandResetPreviewRequestId) {
                    this.grandResetPreviewError = this.resetPreviewConfiguration.grandResetPreviewFailed;
                }
            } finally {
                if (requestId === this.grandResetPreviewRequestId) {
                    this.grandResetPreviewLoading = false;
                }
            }
        },

        /**
         * Shows the final grand-reset confirmation for an eligible writable preview.
         */
        openGrandResetConfirmation() {
            if (
                this.grandResetPreview === null
                || !this.grandResetPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.grandResetConfirmationOpen = true;
            this.grandResetPreviewError = null;
        },

        /**
         * Closes the grand-reset confirmation while no request is running.
         */
        closeGrandResetConfirmation() {
            if (this.grandResetExecuting) {
                return;
            }

            this.grandResetConfirmationOpen = false;
        },

        /**
         * Executes the confirmed grand reset with an at-most-once operation key.
         */
        async executeGrandReset() {
            if (
                this.grandResetExecuting
                || this.grandResetPreview === null
                || !this.grandResetPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.grandResetExecuting = true;
            this.grandResetPreviewError = null;
            this.grandResetSuccessMessage = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.grandResetExecuteUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                            idempotencyKey: this.createIdempotencyKey(),
                        }),
                    },
                );
                const responsePayload = await response.json();

                if (!response.ok) {
                    const reasonMessages = this.resetPreviewConfiguration.grandResetBlockReasons;

                    this.grandResetPreviewError = reasonMessages[responsePayload.reason]
                        ?? responsePayload.message
                        ?? this.resetPreviewConfiguration.grandResetExecutionFailed;

                    return;
                }

                const result = responsePayload.result;

                this.selectedCharacter.level = result.afterLevel;
                this.selectedCharacter.resets = result.afterResets;
                this.selectedCharacter.grandResets = result.afterGrandResets;
                this.selectedCharacter.zen = result.afterZen;
                this.selectedCharacter.points = result.afterPoints;
                this.applyCharacterStats(result.afterStats);
                this.grandResetConfirmationOpen = false;
                this.grandResetSuccessMessage = responsePayload.message
                    ?? this.resetPreviewConfiguration.grandResetCompleted;

                await this.loadGrandResetPreview();
            } catch {
                this.grandResetPreviewError = this.resetPreviewConfiguration.grandResetExecutionFailed;
            } finally {
                this.grandResetExecuting = false;
            }
        },

        /**
         * Loads a fresh ownership-scoped stat-reset preview for the selected character.
         */
        async loadStatResetPreview() {
            if (this.resetPreviewConfiguration === null) {
                return;
            }

            const requestId = this.statResetPreviewRequestId + 1;

            this.statResetPreviewRequestId = requestId;
            this.statResetPreviewLoading = true;
            this.statResetPreviewError = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.statResetPreviewUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                        }),
                    },
                );

                if (!response.ok) {
                    throw new Error(
                        `Stat-reset preview request failed with status ${response.status}.`,
                    );
                }

                const responsePayload = await response.json();

                if (requestId !== this.statResetPreviewRequestId) {
                    return;
                }

                this.statResetPreview = responsePayload.preview;
            } catch {
                if (requestId === this.statResetPreviewRequestId) {
                    this.statResetPreviewError = this.resetPreviewConfiguration.statResetPreviewFailed;
                }
            } finally {
                if (requestId === this.statResetPreviewRequestId) {
                    this.statResetPreviewLoading = false;
                }
            }
        },

        /**
         * Shows the final stat-reset confirmation for an eligible writable preview.
         */
        openStatResetConfirmation() {
            if (
                this.statResetPreview === null
                || !this.statResetPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.statResetConfirmationOpen = true;
            this.statResetPreviewError = null;
        },

        /**
         * Closes the stat-reset confirmation while no request is running.
         */
        closeStatResetConfirmation() {
            if (this.statResetExecuting) {
                return;
            }

            this.statResetConfirmationOpen = false;
        },

        /**
         * Executes the confirmed stat reset with an at-most-once operation key.
         */
        async executeStatReset() {
            if (
                this.statResetExecuting
                || this.statResetPreview === null
                || !this.statResetPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.statResetExecuting = true;
            this.statResetPreviewError = null;
            this.statResetSuccessMessage = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.statResetExecuteUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                            idempotencyKey: this.createIdempotencyKey(),
                        }),
                    },
                );
                const responsePayload = await response.json();

                if (!response.ok) {
                    const reasonMessages = this.resetPreviewConfiguration.statResetBlockReasons;

                    this.statResetPreviewError = reasonMessages[responsePayload.reason]
                        ?? responsePayload.message
                        ?? this.resetPreviewConfiguration.statResetExecutionFailed;

                    return;
                }

                const result = responsePayload.result;

                this.selectedCharacter.zen = result.afterZen;
                this.selectedCharacter.points = result.afterPoints;
                this.applyCharacterStats(result.afterStats);
                this.statResetConfirmationOpen = false;
                this.statResetSuccessMessage = responsePayload.message
                    ?? this.resetPreviewConfiguration.statResetCompleted;

                await this.loadStatResetPreview();
            } catch {
                this.statResetPreviewError = this.resetPreviewConfiguration.statResetExecutionFailed;
            } finally {
                this.statResetExecuting = false;
            }
        },

        /**
         * Loads a fresh ownership-scoped PK-clear preview for the selected character.
         */
        async loadPkClearPreview() {
            if (this.resetPreviewConfiguration === null) {
                return;
            }

            const requestId = this.pkClearPreviewRequestId + 1;

            this.pkClearPreviewRequestId = requestId;
            this.pkClearPreviewLoading = true;
            this.pkClearPreviewError = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.pkClearPreviewUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                        }),
                    },
                );

                if (!response.ok) {
                    throw new Error(`PK-clear preview request failed with status ${response.status}.`);
                }

                const responsePayload = await response.json();

                if (requestId !== this.pkClearPreviewRequestId) {
                    return;
                }

                this.pkClearPreview = responsePayload.preview;
            } catch {
                if (requestId === this.pkClearPreviewRequestId) {
                    this.pkClearPreviewError = this.resetPreviewConfiguration.pkClearPreviewFailed;
                }
            } finally {
                if (requestId === this.pkClearPreviewRequestId) {
                    this.pkClearPreviewLoading = false;
                }
            }
        },

        /**
         * Shows the final PK-clear confirmation for an eligible writable preview.
         */
        openPkClearConfirmation() {
            if (
                this.pkClearPreview === null
                || !this.pkClearPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.pkClearConfirmationOpen = true;
            this.pkClearPreviewError = null;
        },

        /**
         * Closes the PK-clear confirmation while no request is running.
         */
        closePkClearConfirmation() {
            if (this.pkClearExecuting) {
                return;
            }

            this.pkClearConfirmationOpen = false;
        },

        /**
         * Executes the confirmed PK clear with an at-most-once operation key.
         */
        async executePkClear() {
            if (
                this.pkClearExecuting
                || this.pkClearPreview === null
                || !this.pkClearPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.pkClearExecuting = true;
            this.pkClearPreviewError = null;
            this.pkClearSuccessMessage = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.pkClearExecuteUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                            idempotencyKey: this.createIdempotencyKey(),
                        }),
                    },
                );
                const responsePayload = await response.json();

                if (!response.ok) {
                    const reasonMessages = this.resetPreviewConfiguration.pkClearBlockReasons;

                    this.pkClearPreviewError = reasonMessages[responsePayload.reason]
                        ?? responsePayload.message
                        ?? this.resetPreviewConfiguration.pkClearExecutionFailed;

                    return;
                }

                this.selectedCharacter.zen = responsePayload.result.afterZen;
                this.pkClearConfirmationOpen = false;
                this.pkClearSuccessMessage = responsePayload.message
                    ?? this.resetPreviewConfiguration.pkClearCompleted;

                await this.loadPkClearPreview();
            } catch {
                this.pkClearPreviewError = this.resetPreviewConfiguration.pkClearExecutionFailed;
            } finally {
                this.pkClearExecuting = false;
            }
        },

        /**
         * Loads a fresh ownership-scoped Move to Town preview.
         */
        async loadMoveToTownPreview() {
            if (this.resetPreviewConfiguration === null) {
                return;
            }

            const requestId = this.moveToTownPreviewRequestId + 1;

            this.moveToTownPreviewRequestId = requestId;
            this.moveToTownPreviewLoading = true;
            this.moveToTownPreviewError = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.moveToTownPreviewUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                        }),
                    },
                );

                if (!response.ok) {
                    throw new Error(`Move preview request failed with status ${response.status}.`);
                }

                const payload = await response.json();

                if (requestId !== this.moveToTownPreviewRequestId) {
                    return;
                }

                this.moveToTownPreview = payload.preview;
            } catch {
                if (requestId === this.moveToTownPreviewRequestId) {
                    this.moveToTownPreviewError = this.resetPreviewConfiguration.moveToTownPreviewFailed;
                }
            } finally {
                if (requestId === this.moveToTownPreviewRequestId) {
                    this.moveToTownPreviewLoading = false;
                }
            }
        },

        /**
         * Shows the final confirmation for an eligible writable move.
         */
        openMoveToTownConfirmation() {
            if (
                this.moveToTownPreview === null
                || !this.moveToTownPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.moveToTownConfirmationOpen = true;
            this.moveToTownPreviewError = null;
        },

        /**
         * Closes the Move to Town confirmation while no request is running.
         */
        closeMoveToTownConfirmation() {
            if (this.moveToTownExecuting) {
                return;
            }

            this.moveToTownConfirmationOpen = false;
        },

        /**
         * Executes the confirmed Move to Town with an at-most-once key.
         */
        async executeMoveToTown() {
            if (
                this.moveToTownExecuting
                || this.moveToTownPreview === null
                || !this.moveToTownPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.moveToTownExecuting = true;
            this.moveToTownPreviewError = null;
            this.moveToTownSuccessMessage = null;

            try {
                const response = await window.fetch(
                    this.resetPreviewConfiguration.moveToTownExecuteUrl,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                        },
                        body: JSON.stringify({
                            characterName: this.selectedCharacter.name,
                            idempotencyKey: this.createIdempotencyKey(),
                        }),
                    },
                );
                const payload = await response.json();

                if (!response.ok) {
                    const labels = this.resetPreviewConfiguration.moveToTownBlockReasons;

                    this.moveToTownPreviewError = labels[payload.reason]
                        ?? payload.message
                        ?? this.resetPreviewConfiguration.moveToTownExecutionFailed;

                    return;
                }

                this.selectedCharacter.zen = payload.result.afterZen;
                this.selectedCharacter.map = payload.result.afterMapName;
                this.moveToTownConfirmationOpen = false;
                this.moveToTownSuccessMessage = payload.message
                    ?? this.resetPreviewConfiguration.moveToTownCompleted;

                await this.loadMoveToTownPreview();
            } catch {
                this.moveToTownPreviewError = this.resetPreviewConfiguration.moveToTownExecutionFailed;
            } finally {
                this.moveToTownExecuting = false;
            }
        },

        /**
         * Creates an RFC 4122 version-four identifier without server-side state.
         *
         * @returns {string}
         */
        createIdempotencyKey() {
            if (typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            const randomBytes = new Uint8Array(16);

            window.crypto.getRandomValues(randomBytes);
            randomBytes[6] = (randomBytes[6] & 0x0f) | 0x40;
            randomBytes[8] = (randomBytes[8] & 0x3f) | 0x80;

            const hexadecimalBytes = [];

            for (const byte of randomBytes) {
                hexadecimalBytes.push(byte.toString(16).padStart(2, '0'));
            }

            return `${hexadecimalBytes.slice(0, 4).join('')}-${hexadecimalBytes.slice(4, 6).join('')}-${hexadecimalBytes.slice(6, 8).join('')}-${hexadecimalBytes.slice(8, 10).join('')}-${hexadecimalBytes.slice(10, 16).join('')}`;
        },

        /**
         * Requests a fresh ownership-scoped allocation preview from Laravel.
         */
        async reviewStatAllocation() {
            if (
                this.resetPreviewConfiguration === null
                || this.allocatedPoints === 0
                || this.statPreviewLoading
            ) {
                return;
            }

            const requestId = this.statPreviewRequestId + 1;

            this.statPreviewRequestId = requestId;
            this.statPreviewLoading = true;
            this.statPreviewError = null;
            this.statSuccessMessage = null;
            this.statConfirmationOpen = false;

            try {
                const response = await window.fetch(this.resetPreviewConfiguration.statPreviewUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                    },
                    body: JSON.stringify({
                        characterName: this.selectedCharacter.name,
                        ...this.allocations,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`Stat preview request failed with status ${response.status}.`);
                }

                const responsePayload = await response.json();

                if (requestId !== this.statPreviewRequestId) {
                    return;
                }

                this.statPreview = responsePayload.preview;
            } catch {
                if (requestId === this.statPreviewRequestId) {
                    this.statPreviewError = this.resetPreviewConfiguration.statPreviewFailed;
                }
            } finally {
                if (requestId === this.statPreviewRequestId) {
                    this.statPreviewLoading = false;
                }
            }
        },

        /**
         * Shows the final allocation confirmation for an eligible writable preview.
         */
        openStatConfirmation() {
            if (
                this.statPreview === null
                || !this.statPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.statConfirmationOpen = true;
            this.statPreviewError = null;
        },

        /**
         * Closes the allocation confirmation while no request is running.
         */
        closeStatConfirmation() {
            if (this.statExecuting) {
                return;
            }

            this.statConfirmationOpen = false;
        },

        /**
         * Applies the confirmed allocation with a unique at-most-once key.
         */
        async executeStatAllocation() {
            if (
                this.statExecuting
                || this.statPreview === null
                || !this.statPreview.eligible
                || !this.resetPreviewConfiguration.writesEnabled
            ) {
                return;
            }

            this.statExecuting = true;
            this.statPreviewError = null;
            this.statSuccessMessage = null;

            try {
                const response = await window.fetch(this.resetPreviewConfiguration.statExecuteUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.resetPreviewConfiguration.csrfToken,
                    },
                    body: JSON.stringify({
                        characterName: this.selectedCharacter.name,
                        idempotencyKey: this.createIdempotencyKey(),
                        ...this.allocations,
                    }),
                });
                const responsePayload = await response.json();

                if (!response.ok) {
                    const reasonMessages = this.resetPreviewConfiguration.statBlockReasons;

                    this.statPreviewError = reasonMessages[responsePayload.reason]
                        ?? responsePayload.message
                        ?? this.resetPreviewConfiguration.statExecutionFailed;

                    return;
                }

                const result = responsePayload.result;

                this.selectedCharacter.points = result.afterPoints;
                this.applyCharacterStats(result.afterStats);
                this.allocations = createEmptyStatAllocations(this.selectedCharacter);
                this.statPreview = null;
                this.statConfirmationOpen = false;
                this.statSuccessMessage = responsePayload.message
                    ?? this.resetPreviewConfiguration.statCompleted;
            } catch {
                this.statPreviewError = this.resetPreviewConfiguration.statExecutionFailed;
            } finally {
                this.statExecuting = false;
            }
        },

        /**
         * Invalidates the server review after the local allocation draft changes.
         */
        clearStatReview() {
            this.statPreviewRequestId += 1;
            this.statPreview = null;
            this.statPreviewLoading = false;
            this.statPreviewError = null;
            this.statConfirmationOpen = false;
            this.statSuccessMessage = null;
        },

        /**
         * Adds points to one stat while preventing the preview from overspending.
         *
         * @param {string} stat The stat key receiving points.
         * @param {number} amount The number of points to add.
         */
        allocatePoints(stat, amount) {
            const allowedAmount = Math.min(amount, this.remainingPoints);

            if (allowedAmount <= 0) {
                return;
            }

            this.allocations[stat] += allowedAmount;
            this.clearStatReview();
        },

        /**
         * Removes points from one pending allocation without changing saved stats.
         *
         * @param {string} stat The stat key losing preview points.
         * @param {number} amount The number of points to remove.
         */
        removePoints(stat, amount) {
            const currentAllocation = this.allocations[stat];
            const allowedAmount = Math.min(amount, currentAllocation);

            this.allocations[stat] -= allowedAmount;
            this.clearStatReview();
        },

        /**
         * Clears all pending stat allocation values.
         */
        resetAllocations() {
            this.allocations = createEmptyStatAllocations(this.selectedCharacter);
            this.clearStatReview();
        },
    };
}

/**
 * Creates live reset calculations and persists audited administration drafts.
 *
 * @param {object} initialSettings The server reset profile loaded from Laravel.
 * @param {object} translations Localized labels used by the live preview.
 * @param {object} persistence Endpoint, CSRF token and current revision metadata.
 * @returns {object} Alpine state for reset settings and their live preview.
 */
export function resetSettings(initialSettings, translations, persistence) {
    return {
        settings: initialSettings,
        translations,
        persistence,
        previewResetNumber: 8,
        revision: persistence.revision,
        revisions: persistence.revisions,
        saving: false,
        validationErrors: {},
        savedNoticeVisible: false,
        savedNoticeIsError: false,
        savedNoticeMessage: translations.saved,
        noticeTimeoutId: null,

        /**
         * Returns the localized outcome for the stat reset policy.
         *
         * @returns {string}
         */
        get statsPolicyLabel() {
            if (this.settings.clearStats) {
                return this.translations.statsReset;
            }

            return this.translations.preserved;
        },

        /**
         * Returns the localized label for the selected inventory policy.
         *
         * @returns {string}
         */
        get inventoryPolicyLabel() {
            const policyLabels = {
                keep_all: this.translations.keepAll,
                clear_bag: this.translations.clearBag,
                clear_all: this.translations.clearAll,
            };

            return policyLabels[this.settings.inventoryPolicy];
        },

        /**
         * Calculates the Zen charge for the selected preview reset number.
         *
         * @returns {number}
         */
        get previewZenCost() {
            if (!this.settings.multiplyCostByReset) {
                return Number(this.settings.baseZenCost);
            }

            return Number(this.settings.baseZenCost) * Number(this.previewResetNumber);
        },

        /**
         * Calculates the level-up points granted by the selected reset profile.
         *
         * @returns {number}
         */
        get previewPoints() {
            if (this.settings.pointsMode === 'fixed') {
                return Number(this.settings.pointsPerReset);
            }

            return Number(this.settings.pointsPerReset) * Number(this.previewResetNumber);
        },

        /**
         * Returns the first server validation message for one settings field.
         *
         * @param {string} field The camel-case request field being rendered.
         * @returns {string|null}
         */
        firstValidationError(field) {
            const messages = this.validationErrors[field];

            if (!Array.isArray(messages) || messages.length === 0) {
                return null;
            }

            return messages[0];
        },

        /**
         * Saves the complete reset policy as one server-owned audit revision.
         */
        async saveSettings() {
            this.saving = true;
            this.validationErrors = {};

            try {
                const response = await window.fetch(this.persistence.saveUrl, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.persistence.csrfToken,
                    },
                    body: JSON.stringify(this.settings),
                });
                const contentType = response.headers.get('content-type') ?? '';
                const responsePayload = contentType.includes('application/json')
                    ? await response.json()
                    : {};

                if (response.status === 422) {
                    this.validationErrors = responsePayload.errors ?? {};
                    this.showSavedNotice(this.translations.saveFailed, true);

                    return;
                }

                if (!response.ok) {
                    throw new Error(`Reset settings request failed with status ${response.status}.`);
                }

                this.settings = responsePayload.settings;
                this.revision = responsePayload.revision;
                this.revisions.unshift({
                    revision: responsePayload.revision,
                    changedAt: this.translations.justNow,
                });
                this.revisions = this.revisions.slice(0, 8);
                this.showSavedNotice(responsePayload.message ?? this.translations.saved, false);
            } catch {
                this.showSavedNotice(this.translations.saveFailed, true);
            } finally {
                this.saving = false;
            }
        },

        /**
         * Shows a temporary success or failure notice for a persistence attempt.
         *
         * @param {string} message The localized notice text.
         * @param {boolean} isError Whether the notice represents a failed save.
         */
        showSavedNotice(message, isError) {
            if (this.noticeTimeoutId !== null) {
                window.clearTimeout(this.noticeTimeoutId);
            }

            this.savedNoticeMessage = message;
            this.savedNoticeIsError = isError;
            this.savedNoticeVisible = true;

            this.noticeTimeoutId = window.setTimeout(
                this.hideSavedNotice.bind(this),
                2200,
            );
        },

        /**
         * Hides the reset-setting saved-state notice.
         */
        hideSavedNotice() {
            this.savedNoticeVisible = false;
            this.noticeTimeoutId = null;
        },
    };
}
