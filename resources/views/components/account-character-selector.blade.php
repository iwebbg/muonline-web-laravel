<div {{ $attributes->class(['flex gap-3 overflow-x-auto pb-2']) }}>
    <template x-for="(character, characterIndex) in characters" :key="character.name">
        <button
            type="button"
            class="character-selector"
            :class="selectedCharacterIndex === characterIndex && 'character-selector-active'"
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
</div>
