@props(['compact' => false, 'showLabel' => true])

<div
    x-data="rankingLevelDetails()"
    x-id="['ranking-level-details']"
    x-on:mouseenter="show($refs.levelTrigger, entry)"
    x-on:mouseleave="close()"
    x-on:focusout="close()"
    x-on:keydown.escape.stop="close()"
    x-on:scroll.window="close()"
    x-on:resize.window="close()"
    x-on:click.outside="close()"
    @class([
        'text-right' => $compact,
        'rounded-xl border border-white/8 bg-white/[0.025] text-center' => ! $compact,
    ])
>
    <template x-if="entry.masterLevel !== undefined">
        <button
            type="button"
            x-ref="levelTrigger"
            x-on:focus="show($el, entry)"
            x-on:click.stop.prevent="show($el, entry)"
            x-bind:aria-describedby="detailsOpen ? $id('ranking-level-details') : null"
            x-bind:aria-expanded="detailsOpen"
            aria-label="{{ __('ui.rankings.level_details') }}"
            @class([
                'rounded-xl transition hover:bg-gold/10 hover:text-gold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold',
                'px-2 py-1 -mr-2 -my-1 text-right' => $compact,
                'w-full p-3 text-center' => ! $compact,
            ])
        >
            <strong @class(['tabular-nums text-white', 'block' => $showLabel, 'inline-block' => ! $showLabel]) x-text="entry.displayLevel ?? entry.level"></strong>
            <span @class(['inline-flex items-center gap-1 text-[0.6rem] uppercase tracking-widest text-steel', 'mt-1' => $showLabel, 'ml-1 align-middle' => ! $showLabel])>
                @if ($showLabel)
                    {{ __('ui.profiles.level') }}
                @endif
                <svg class="size-3 text-gold/75" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <circle cx="8" cy="8" r="6" stroke="currentColor" />
                    <path d="M8 7v4M8 4.5v.5" stroke="currentColor" stroke-linecap="round" />
                </svg>
            </span>
        </button>
    </template>
    <template x-if="entry.masterLevel === undefined">
        <div @class(['p-3' => ! $compact])>
            <strong class="block text-white" x-text="entry.level"></strong>
            <span class="mt-1 block text-[0.6rem] uppercase tracking-widest text-steel">{{ __('ui.profiles.level') }}</span>
        </div>
    </template>

    <template x-teleport="body">
        <div
            x-show="detailsOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            x-bind:id="$id('ranking-level-details')"
            x-bind:style="positionStyle"
            role="tooltip"
            class="pointer-events-none fixed z-[100] w-64 max-w-[calc(100vw-1.5rem)] rounded-2xl border border-gold/25 bg-[#0e1117] p-4 text-left shadow-2xl shadow-black/60"
        >
            <div class="flex items-center justify-between gap-3 border-b border-white/8 pb-3">
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-gold">{{ __('ui.rankings.level_details') }}</p>
                    <p class="mt-1 truncate text-sm text-silver" x-text="entry.name"></p>
                </div>
                <strong class="text-2xl tabular-nums text-white" x-text="entry.displayLevel ?? entry.level"></strong>
            </div>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between rounded-lg bg-white/[0.04] px-3 py-2">
                    <dt class="text-silver">{{ __('ui.rankings.normal_level') }}</dt>
                    <dd class="font-semibold tabular-nums text-white" x-text="entry.level"></dd>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-gold/[0.07] px-3 py-2">
                    <dt class="text-gold">{{ __('ui.account.master_level') }}</dt>
                    <dd class="font-semibold tabular-nums text-gold" x-text="entry.masterLevel"></dd>
                </div>
            </dl>
        </div>
    </template>
</div>
