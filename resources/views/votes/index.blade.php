@extends('layouts.app')

@section('title', __('ui.votes.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <x-account-feature-header
            :kicker="__('ui.votes.kicker')"
            :heading="__('ui.votes.heading')"
            :intro="__('ui.votes.intro')"
        />

        @if ($errors->has('voteClaim'))
            <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
                {{ $errors->first('voteClaim') }}
            </div>
        @endif

        <div class="mt-6 rounded-2xl border border-gold/20 bg-gold/5 p-4 text-sm text-silver">
            {{ __('ui.votes.manual_notice') }}
        </div>

        <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($voteCards as $card)
                @php($site = $card['site'])
                <article class="panel-card flex min-h-80 flex-col overflow-hidden">
                    @if ($site->image_url)
                        <div class="flex h-28 items-center justify-center border-b border-white/8 bg-black/20 p-5">
                            <img src="{{ $site->image_url }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                        </div>
                    @endif
                    <div class="flex flex-1 flex-col p-6">
                        <p class="kicker">{{ __('ui.votes.cooldown', ['hours' => $site->cooldown_hours]) }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-white">{{ $site->localizedName() }}</h2>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-white/8 bg-black/20 p-3">
                                <span class="block text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.votes.credits') }}</span>
                                <strong class="mt-2 block text-lg text-white">{{ number_format($site->reward_credits) }}</strong>
                            </div>
                            <div class="rounded-xl border border-white/8 bg-black/20 p-3">
                                <span class="block text-xs uppercase tracking-[0.14em] text-steel">{{ __('ui.votes.zen') }}</span>
                                <strong class="mt-2 block text-lg text-white">{{ number_format($site->reward_zen) }}</strong>
                            </div>
                        </div>

                        <div class="mt-auto pt-6">
                            @if ($card['pendingClaim'])
                                <span class="button button-ghost w-full cursor-not-allowed justify-center opacity-60">
                                    {{ __('ui.votes.pending') }}
                                </span>
                            @elseif ($card['availableAt'])
                                <span class="button button-ghost w-full cursor-not-allowed justify-center opacity-60">
                                    {{ __('ui.votes.available_at', ['time' => $card['availableAt']->format('Y-m-d H:i')]) }}
                                </span>
                            @else
                                <form method="POST" action="{{ route('votes.claims.store', $site) }}" target="_blank">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ $card['idempotencyKey'] }}">
                                    <button type="submit" class="button button-primary w-full">
                                        {{ __('ui.votes.visit') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="panel-card p-8 text-center text-steel md:col-span-2 xl:col-span-3">
                    {{ __('ui.votes.no_sites') }}
                </div>
            @endforelse
        </section>

        <section class="mt-12">
            <p class="kicker">{{ __('ui.votes.history') }}</p>
            <div class="panel-card mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-white/8 text-steel">
                        <tr>
                            <th class="p-4">{{ __('ui.votes.site') }}</th>
                            <th class="p-4">{{ __('ui.votes.reward') }}</th>
                            <th class="p-4">{{ __('ui.votes.status') }}</th>
                            <th class="p-4">{{ __('ui.votes.created') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($claims as $claim)
                            <tr class="border-b border-white/5 last:border-0">
                                <td class="p-4 text-white">{{ $claim->site->localizedName() }}</td>
                                <td class="p-4 text-silver">
                                    {{ number_format($claim->reward_credits) }} Credits · {{ number_format($claim->reward_zen) }} Zen
                                </td>
                                <td class="p-4 text-silver">{{ __('ui.votes.statuses.'.$claim->status->value) }}</td>
                                <td class="whitespace-nowrap p-4 text-steel">{{ $claim->initiated_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-steel">{{ __('ui.votes.no_history') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
