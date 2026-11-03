<div {{ $attributes->class(['flex gap-3 overflow-x-auto pb-2']) }}>
    <template x-for="(character, characterIndex) in characters" :key="character.name">
        <button
            type="button"
            class="character-selector"
            :class="activeSection !== 'vaults' && selectedCharacterIndex === characterIndex && 'character-selector-active'"
            @click="selectCharacter(characterIndex)"
        >
            <img :src="character.image" :alt="character.class" class="size-11 rounded-xl object-cover object-top">
            <span class="text-left">
                <strong class="block text-sm text-white" x-text="character.name"></strong>
                <span class="block text-xs text-steel" x-text="character.class"></span>
            </span>
            <span class="ml-3 status-dot status-dot-small" :class="!character.online && 'status-dot-offline'"></span>
        </button>
    </template>
    <button
        type="button"
        class="character-selector shrink-0"
        :class="activeSection === 'vaults' && 'character-selector-active'"
        @click="selectAccountVaults()"
    >
        <span class="grid size-11 place-items-center rounded-xl border border-gold/25 bg-gold/8 text-gold" aria-hidden="true">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M3 9.5 12 4l9 5.5v9A1.5 1.5 0 0 1 19.5 20h-15A1.5 1.5 0 0 1 3 18.5v-9Z" />
                <path d="M7 12h10M7 16h10" />
            </svg>
        </span>
        <span class="text-left">
            <strong class="block text-sm text-white">{{ __('ui.account.vaults') }}</strong>
            <span class="block text-xs text-steel">{{ __('ui.account.account_storage') }}</span>
        </span>
    </button>
</div>
