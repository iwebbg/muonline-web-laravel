@extends('layouts.app')

@section('title', __('ui.profiles.character_title', ['name' => $character->name]))

@section('content')
    <section class="relative overflow-hidden border-b border-white/8">
        <div class="absolute inset-0 bg-[url('/images/backgrounds/default.jpg')] bg-cover bg-center opacity-25"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-ink/35 via-ink/85 to-ink"></div>
        <div class="shell relative py-12 sm:py-16">
            <a href="{{ route('rankings') }}" class="text-sm text-steel transition hover:text-white">← {{ __('ui.profiles.back_to_rankings') }}</a>
            <div class="mt-7 grid gap-8 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-end">
                <div class="overflow-hidden rounded-3xl border border-white/10 bg-black/30">
                    <img src="{{ $character->image }}" alt="" class="aspect-[4/5] w-full object-cover" fetchpriority="high">
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span @class([
                            'rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em]',
                            'border-emerald-300/25 text-emerald-200' => $character->online,
                            'border-white/10 text-steel' => ! $character->online,
                        ])>
                            {{ $character->online ? __('ui.rankings.online') : __('ui.rankings.offline') }}
                        </span>
                        @if ($character->vip)
                            <span class="rounded-full border border-gold/25 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-gold">VIP</span>
                        @endif
                    </div>
                    <p class="kicker mt-6">{{ $character->className }}</p>
                    <h1 class="mt-3 font-display text-5xl text-white sm:text-7xl">{{ $character->name }}</h1>
                    <p class="mt-4 text-lg text-silver">{{ __('ui.profiles.character_intro', ['map' => $character->map]) }}</p>
                    @if ($character->guildName)
                        <a href="{{ route('guilds.show', $character->guildName) }}" class="button button-ghost mt-6">
                            {{ __('ui.profiles.guild_label') }}: {{ $character->guildName }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="shell py-10 lg:py-14">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                [__('ui.profiles.level'), $character->level],
                [__('ui.home.resets'), $character->resets],
                [__('ui.home.grand'), $character->grandResets],
                [__('ui.profiles.location'), $character->map],
                [__('ui.profiles.pk_level'), $character->pkLevel],
            ] as [$label, $value])
                <article class="panel-card p-6">
                    <span class="text-xs uppercase tracking-[0.16em] text-steel">{{ $label }}</span>
                    <strong class="mt-3 block text-2xl text-white">{{ is_int($value) ? number_format($value) : $value }}</strong>
                </article>
            @endforeach
        </div>
        <div class="mt-8 rounded-2xl border border-white/8 bg-white/[0.025] p-5 text-sm text-steel">
            {{ __('ui.profiles.privacy_notice') }}
        </div>
    </section>
@endsection
