@props(['snapshot'])

<details class="panel-card mt-6 p-5">
    <summary class="flex cursor-pointer list-none flex-wrap items-start justify-between gap-4">
        <div>
            <p class="kicker">Battle Core</p>
            <h2 id="battle-core-heading" class="mt-2 text-lg font-semibold text-white">{{ __('ui.account.battle_core_heading') }}</h2>
        </div>
        @if ($snapshot->available)
            <div class="resource-chip">
                <span>{{ __('ui.account.battle_core_rewards') }}</span>
                <strong>{{ number_format($snapshot->unclaimedRewardItems) }}</strong>
            </div>
        @endif
    </summary>
    <p class="mt-4 text-sm text-silver">{{ __('ui.account.battle_core_help') }}</p>
    @if (! $snapshot->available)
        <p class="mt-4 text-sm text-steel">{{ __('ui.account.battle_core_unavailable') }}</p>
    @else
        <div class="mt-4 flex flex-wrap gap-3">
            @forelse ($snapshot->registrations as $registration)
                <div class="rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                    <p class="font-semibold text-white">{{ $registration['name'] }}</p>
                    <p class="mt-1 text-sm text-silver">
                        {{ __('ui.account.battle_core_states.'.match ($registration['registered']) { true => 'registered', false => 'not_registered', default => 'unknown' }) }}
                    </p>
                    @if ($registration['month'] >= 1 && $registration['month'] <= 12 && $registration['day'] >= 1 && $registration['day'] <= 31)
                        <p class="mt-1 text-xs text-steel">{{ __('ui.account.battle_core_date', ['day' => $registration['day'], 'month' => $registration['month']]) }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-steel">{{ __('ui.account.battle_core_empty') }}</p>
            @endforelse
        </div>
    @endif
</details>
