@extends('layouts.app')

@section('title', __('ui.native_services.title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <h1 class="font-display text-4xl text-white">{{ __('ui.native_services.title') }}</h1>
        <p class="mt-3 max-w-3xl text-silver">{{ __('ui.native_services.intro') }}</p>
        <x-account-navigation class="mt-6" />
        @if (session('status'))
            <p class="panel-card mt-6 p-4 text-emerald-200" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="panel-card mt-6 p-4 text-red-200" role="alert">{{ $errors->first() }}</p>
        @endif
        <div class="mt-6 flex flex-wrap gap-3">
            <div class="resource-chip"><span>Credits</span><strong>{{ number_format($walletBalance) }}</strong></div>
            @foreach (['WCoin(C)' => $balances->wCoinC, 'WCoin(P)' => $balances->wCoinP, 'Goblin Points' => $balances->goblinPoints, 'Lucky Coins' => $balances->luckyCoins] as $label => $balance)
                <div class="resource-chip"><span>{{ $label }}</span><strong>{{ number_format($balance) }}</strong></div>
            @endforeach
        </div>
        <p class="mt-5 text-sm text-gold">{{ __('ui.native_services.offline_notice') }}</p>
        @if (! $writesEnabled)
            <p class="mt-2 text-sm text-steel">{{ __('ui.native_services.errors.writes_disabled') }}</p>
        @endif

        @if ($exchangeEnabled)
            <section class="mt-8" aria-labelledby="native-exchange-heading">
                <h2 id="native-exchange-heading" class="text-2xl font-semibold text-white">{{ __('ui.native_services.exchange_heading') }}</h2>
                <p class="mt-2 text-sm text-silver">{{ __('ui.native_services.exchange_help') }}</p>
                <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($rates as $rate)
                        <form method="POST" action="{{ route('account.native-services.exchange') }}" class="panel-card flex flex-col gap-4 p-5" x-data="{ units: {{ $rate->minimum_units }} }">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="currency" value="{{ $rate->currency->value }}">
                            <h3 class="text-lg font-semibold text-white">{{ $rate->currency->label() }}</h3>
                            <p class="text-sm text-gold">{{ number_format($rate->credit_units) }} Credits → {{ number_format($rate->coin_units) }} {{ $rate->currency->label() }}</p>
                            <label class="field-group"><span>{{ __('ui.native_services.units') }}</span><input type="number" name="units" min="{{ $rate->minimum_units }}" max="{{ min($rate->maximum_units, intdiv(2147483647, $rate->coin_units)) }}" value="{{ $rate->minimum_units }}" x-model.number="units" required></label>
                            @if ($rate->currency === \App\Economy\NativeCurrency::Ruud)
                                <label class="field-group"><span>{{ __('ui.native_services.character') }}</span>
                                    <select name="character_name" required>
                                        @foreach ($characters as $character)
                                            <option value="{{ $character->name }}">{{ $character->name }} · {{ number_format($balances->ruudByCharacter[$character->name] ?? 0) }} Ruud</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <p class="text-sm text-silver"><span x-text="(Number(units) * {{ $rate->credit_units }}).toLocaleString()"></span> Credits → <span x-text="(Number(units) * {{ $rate->coin_units }}).toLocaleString()"></span> {{ $rate->currency->label() }}</p>
                            <button type="submit" class="button button-primary mt-auto" @disabled(! $writesEnabled || ($rate->currency === \App\Economy\NativeCurrency::Ruud && $characters === []))>{{ __('ui.native_services.exchange') }}</button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($vipEnabled)
            <section class="mt-8" aria-labelledby="native-vip-heading">
                <h2 id="native-vip-heading" class="text-2xl font-semibold text-white">{{ __('ui.native_services.vip_heading') }}</h2>
                <p class="mt-2 text-sm text-silver">{{ __('ui.native_services.vip_help') }}</p>
                @if ($membership !== null)
                    <p class="mt-3 text-gold">{{ __('ui.native_services.vip_current', ['level' => $membership->effectiveLevel ?? '?']) }}</p>
                    @if (($membership->effectiveLevel ?? 0) > 0)
                        <p class="mt-1 text-sm text-silver">{{ __('ui.account.native_membership_expiry', ['date' => $membership->expiresAt]) }}</p>
                    @endif
                @endif
                <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($plans as $plan)
                        <form method="POST" action="{{ route('account.native-services.vip') }}" class="panel-card flex flex-col gap-4 p-5">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="plan_id" value="{{ $plan->getKey() }}">
                            <h3 class="text-lg font-semibold text-white">{{ $plan->localizedName(app()->getLocale()) }}</h3>
                            <p class="text-sm text-silver">{{ $plan->localizedDescription(app()->getLocale()) }}</p>
                            <p class="text-sm text-gold">{{ __('ui.native_services.vip_terms', ['level' => $plan->native_account_level, 'days' => $plan->duration_days, 'credits' => number_format($plan->price_credits)]) }}</p>
                            <button type="submit" class="button button-primary mt-auto" @disabled(! $writesEnabled || (($membership?->effectiveLevel ?? 0) > 0 && $membership->effectiveLevel !== $plan->native_account_level))>{{ __('ui.native_services.buy_vip') }}</button>
                        </form>
                    @empty
                        <p class="text-sm text-steel">{{ __('ui.native_services.no_vip_plans') }}</p>
                    @endforelse
                </div>
            </section>
        @endif

        <section class="panel-card mt-8 overflow-x-auto p-5" aria-labelledby="native-purchase-history">
            <h2 id="native-purchase-history" class="text-xl font-semibold text-white">{{ __('ui.native_services.history') }}</h2>
            <p class="mt-2 text-sm text-silver">{{ __('ui.native_services.recovery_help') }}</p>
            <table class="mt-4 w-full text-left text-sm">
                <thead><tr class="text-steel"><th class="p-2">{{ __('ui.native_services.service') }}</th><th class="p-2">Credits</th><th class="p-2">{{ __('ui.native_services.status') }}</th><th class="p-2">{{ __('ui.native_services.character') }}</th><th class="p-2"></th></tr></thead>
                <tbody>
                    @foreach ($purchases as $purchase)
                        <tr class="border-t border-white/8 text-silver">
                            <td class="p-2">{{ __('ui.native_services.kinds.'.$purchase->kind->value) }}<x-native-account-purchase-summary :purchase="$purchase" /></td><td class="p-2">{{ number_format($purchase->credit_cost) }}</td><td class="p-2">{{ __('ui.native_services.states.'.$purchase->status->value) }}</td><td class="p-2">{{ $purchase->character_name ?? '—' }}</td>
                            <td class="p-2">
                                @if ($purchase->status === \App\Game\Operations\CharacterOperationStatus::Pending)
                                    <form method="POST" action="{{ route('account.native-services.resume', $purchase) }}">@csrf<button type="submit" class="button button-ghost" @disabled(! $writesEnabled)>{{ __('ui.native_services.resume') }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
@endsection
