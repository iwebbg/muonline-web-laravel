<aside {{ $attributes->class(['character-portrait-card']) }}>
    <div class="absolute inset-x-10 top-14 h-48 rounded-full bg-crimson/20 blur-3xl" aria-hidden="true"></div>
    <img
        :src="selectedCharacter.image"
        :alt="selectedCharacter.class"
        class="relative z-10 h-[27rem] w-full object-cover object-top"
    >
    <div class="relative z-20 -mt-28 bg-gradient-to-t from-[#0b0d12] via-[#0b0d12]/95 to-transparent px-6 pb-6 pt-28">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="kicker" x-text="selectedCharacter.class"></p>
                <h2 class="mt-1 font-display text-3xl text-white" x-text="selectedCharacter.name"></h2>
            </div>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-silver" x-text="selectedCharacter.map"></span>
        </div>
        <div class="mt-5 grid grid-cols-3 gap-2">
            <div class="portrait-metric"><strong x-text="selectedCharacter.level"></strong><span>{{ __('ui.account.level') }}</span></div>
            <div class="portrait-metric"><strong x-text="selectedCharacter.resets"></strong><span>{{ __('ui.account.resets') }}</span></div>
            <div class="portrait-metric"><strong x-text="selectedCharacter.grandResets"></strong><span>{{ __('ui.account.grand') }}</span></div>
        </div>
    </div>
</aside>
