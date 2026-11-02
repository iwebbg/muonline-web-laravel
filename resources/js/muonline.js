/**
 * Creates the responsive navigation state shared by all application areas.
 *
 * @returns {{mobileMenuOpen: boolean, toggleMobileMenu: Function, closeMobileMenu: Function}}
 */
export function applicationNavigation() {
    return {
        mobileMenuOpen: false,

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
 * Creates the interactive character, stat allocation and account tab state.
 *
 * @param {Array<object>} characters Character records available to the account.
 * @param {object|null} resetPreviewConfiguration Reset preview endpoint and localized labels.
 * @returns {object} Alpine state for the account character panel.
 */
export function accountPanel(characters, resetPreviewConfiguration = null) {
    return {
        characters,
        resetPreviewConfiguration,
        selectedCharacterIndex: 0,
        activeSection: 'overview',
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
        allocations: {
            strength: 0,
            agility: 0,
            vitality: 0,
            energy: 0,
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
                            characterName: this.selectedCharacter.name,
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
         * Builds the item title with its level and excellent classification.
         *
         * @param {object} item A decoded 97d+99i item.
         * @returns {string}
         */
        itemTitle(item) {
            const labels = this.resetPreviewConfiguration.itemOptionLabels;
            const level = item.level > 0 ? ` +${item.level}` : '';
            const prefix = item.excellentMask > 0 && item.excellentType !== 4
                ? `${labels.excellentPrefix} `
                : '';

            return `${prefix}${item.name}${level}`;
        },

        /**
         * Formats the current durability value for an item tooltip.
         *
         * @param {object} item A decoded 97d+99i item.
         * @returns {string}
         */
        itemDurabilityText(item) {
            const label = this.resetPreviewConfiguration.itemOptionLabels.durability;

            return `${label}: ${item.durability}`;
        },

        /**
         * Formats the version-specific additional option encoded on an item.
         *
         * @param {object} item A decoded 97d+99i item.
         * @returns {string|null}
         */
        itemAdditionalOption(item) {
            if (item.optionLevel <= 0) {
                return null;
            }

            const labels = this.resetPreviewConfiguration.itemOptionLabels;

            if (item.optionType === 1) {
                return `${labels.automaticHpRecovery} +${item.optionLevel}%`;
            }

            if (item.optionType === 2) {
                return `${labels.additionalDefenseRate} +${item.optionLevel * 5}`;
            }

            const label = item.excellentType === 1
                ? labels.additionalDefense
                : labels.additionalDamage;

            return `${label} +${item.optionLevel * 4}`;
        },

        /**
         * Returns every excellent option enabled by the item's six-bit mask.
         *
         * @param {object} item A decoded 97d+99i item.
         * @returns {Array<string>}
         */
        itemExcellentOptions(item) {
            if (item.excellentMask <= 0) {
                return [];
            }

            const optionGroups = this.resetPreviewConfiguration.itemOptionLabels.excellent;
            let optionGroup = optionGroups.weapon;

            if (item.excellentType === 4) {
                optionGroup = optionGroups.wing;
            } else if (item.excellentType === 1) {
                optionGroup = optionGroups.armor;
            }

            const enabledOptions = [];

            for (let optionIndex = 0; optionIndex < 6; optionIndex += 1) {
                const optionEnabled = (item.excellentMask & (1 << optionIndex)) !== 0;
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
         * @param {object} item A decoded 97d+99i item.
         * @returns {string}
         */
        itemAncientOption(item) {
            const label = this.resetPreviewConfiguration.itemOptionLabels.ancientOption;

            return `${label}: ${item.ancientOption}`;
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
                this.selectedCharacter.strength = result.resultingStats.strength;
                this.selectedCharacter.agility = result.resultingStats.agility;
                this.selectedCharacter.vitality = result.resultingStats.vitality;
                this.selectedCharacter.energy = result.resultingStats.energy;
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
                this.selectedCharacter.strength = result.afterStats.strength;
                this.selectedCharacter.agility = result.afterStats.agility;
                this.selectedCharacter.vitality = result.afterStats.vitality;
                this.selectedCharacter.energy = result.afterStats.energy;
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
                this.selectedCharacter.strength = result.afterStats.strength;
                this.selectedCharacter.agility = result.afterStats.agility;
                this.selectedCharacter.vitality = result.afterStats.vitality;
                this.selectedCharacter.energy = result.afterStats.energy;
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
                this.selectedCharacter.strength = result.afterStats.strength;
                this.selectedCharacter.agility = result.afterStats.agility;
                this.selectedCharacter.vitality = result.afterStats.vitality;
                this.selectedCharacter.energy = result.afterStats.energy;
                this.allocations = {
                    strength: 0,
                    agility: 0,
                    vitality: 0,
                    energy: 0,
                };
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
            this.allocations = {
                strength: 0,
                agility: 0,
                vitality: 0,
                energy: 0,
            };
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
