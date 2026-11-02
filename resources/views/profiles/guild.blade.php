@extends('layouts.app')

@section('title', __('ui.profiles.guild_title', ['name' => $guild->name]))

@section('content')
    <section class="relative overflow-hidden border-b border-white/8">
        <div class="absolute inset-0 bg-[url('/images/backgrounds/default.jpg')] bg-cover bg-center opacity-25"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-ink/35 via-ink/85 to-ink"></div>
        <div class="shell relative py-14 sm:py-20">
            <a href="{{ route('rankings') }}" class="text-sm text-steel transition hover:text-white">← {{ __('ui.profiles.back_to_rankings') }}</a>
            <p class="kicker mt-8">{{ __('ui.profiles.guild_profile') }}</p>
            <h1 class="mt-4 font-display text-5xl text-white sm:text-7xl">{{ $guild->name }}</h1>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('characters.show', $guild->master) }}" class="button button-ghost">
                    {{ __('ui.rankings.master') }}: {{ $guild->master }}
                </a>
                <span class="button button-ghost cursor-default">{{ __('ui.rankings.score') }}: {{ number_format($guild->score) }}</span>
                <span class="button button-ghost cursor-default">{{ __('ui.rankings.members') }}: {{ count($guild->members) }}</span>
            </div>
            @if ($guild->notice !== '')
                <blockquote class="mt-8 max-w-3xl border-l-2 border-gold/40 pl-5 text-lg text-silver">{{ $guild->notice }}</blockquote>
            @endif
        </div>
    </section>

    <section class="shell py-10 lg:py-14">
        <p class="kicker">{{ __('ui.profiles.guild_roster') }}</p>
        <div class="panel-card mt-5 overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-white/8 text-xs uppercase tracking-[0.14em] text-steel">
                    <tr>
                        <th class="px-5 py-4">{{ __('ui.rankings.character') }}</th>
                        <th class="px-5 py-4">{{ __('ui.rankings.class') }}</th>
                        <th class="px-5 py-4 text-right">{{ __('ui.profiles.level') }}</th>
                        <th class="px-5 py-4 text-right">{{ __('ui.home.resets') }}</th>
                        <th class="px-5 py-4 text-right">{{ __('ui.home.grand') }}</th>
                        <th class="px-5 py-4 text-right">{{ __('ui.rankings.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/8">
                    @forelse ($guild->members as $member)
                        <tr class="transition hover:bg-white/[0.025]">
                            <td class="px-5 py-4 font-semibold text-white">
                                <a href="{{ route('characters.show', $member->name) }}" class="transition hover:text-gold">{{ $member->name }}</a>
                                @if ($member->master)
                                    <span class="ml-2 text-xs uppercase tracking-[0.12em] text-gold">{{ __('ui.profiles.guild_master_badge') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-silver">{{ $member->className }}</td>
                            <td class="px-5 py-4 text-right text-white">{{ number_format($member->level) }}</td>
                            <td class="px-5 py-4 text-right text-white">{{ number_format($member->resets) }}</td>
                            <td class="px-5 py-4 text-right text-white">{{ number_format($member->grandResets) }}</td>
                            <td class="px-5 py-4 text-right {{ $member->online ? 'text-emerald-200' : 'text-steel' }}">
                                {{ $member->online ? __('ui.rankings.online') : __('ui.rankings.offline') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-steel">{{ __('ui.profiles.no_guild_members') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
