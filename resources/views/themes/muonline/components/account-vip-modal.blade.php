@props([
    'characters',
    'plans',
    'entitlements',
    'activeEntitlementCount',
    'purchases',
    'balance',
    'idempotencyKey',
    'gameWritesEnabled',
    'showTrigger' => true,
])

<div
    {{ $attributes->class('contents') }}
    x-data="vipPurchaseManager(
        {{ Js::from($plans) }},
        @js((string) old('vip_plan_id', $plans[0]['id'] ?? '')),
        @js((string) old('character_name', $characters[0]['name'] ?? '')),
        @js($errors->has('vip') || request()->query('panel') === 'vip'),
    )"
    @open-account-vip.window="vipOpen = true"
>
    @if ($showTrigger)
        <button type="button" class="resource-chip text-left" x-ref="vipTrigger" @click="vipOpen = true">
            <span>{{ __('ui.account.vip') }}</span>
            <strong class="text-gold">{{ $activeEntitlementCount }}</strong>
        </button>
    @endif

    <template x-teleport="body">
        <div
            class="fixed inset-0 z-[100] flex items-start justify-center overflow-y-auto bg-black/80 p-4 backdrop-blur-sm sm:p-6"
            x-show="vipOpen"
            x-transition.opacity
            x-cloak
            @click.self="vipOpen = false"
            @keydown.escape.window="confirmationOpen ? confirmationOpen = false : vipOpen = false"
        >
            <section class="panel-card my-auto w-full max-w-5xl shadow-2xl shadow-black/60" role="dialog" aria-modal="true" aria-labelledby="account-vip-title">
                <div class="flex items-start justify-between gap-5 border-b border-white/8 pb-5">
                    <div>
                        <p class="kicker">{{ __('ui.account.vip_kicker') }}</p>
                        <h2 id="account-vip-title" class="mt-2 text-2xl font-semibold text-white">{{ __('ui.account.vip_heading') }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-silver">{{ __('ui.account.vip_help') }}</p>
                    </div>
                    <button type="button" class="icon-button shrink-0" aria-label="{{ __('ui.account.vip_close') }}" @click="vipOpen = false">×</button>
                </div>

                @if ($errors->has('vip'))
                    <div class="mt-5 rounded-xl border border-red-300/25 bg-red-300/8 px-4 py-3 text-sm text-red-100" role="alert">
                        {{ $errors->first('vip') }}
                    </div>
                @endif

                <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <form method="POST" action="{{ route('account.vip-purchases.store') }}" class="grid content-start gap-5" x-ref="vipPurchaseForm" @submit.prevent="confirmationOpen = true">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                        <div class="grid gap-5 sm:grid-cols-2">
                            <label class="field-group">
                                <span>{{ __('ui.account.vip_character') }}</span>
                                <select name="character_name" x-model="selectedCharacter" required>
                                    @foreach ($characters as $character)
                                        <option value="{{ $character['name'] }}" @disabled($character['online'])>
                                            {{ $character['name'] }}{{ $character['online'] ? ' · '.__('ui.account.vip_character_online') : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field-group">
                                <span>{{ __('ui.account.vip_plan') }}</span>
                                <select name="vip_plan_id" x-model="selectedPlanId" required>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan['id'] }}">
                                            {{ $plan['name'] }} · {{ number_format($plan['priceCredits']) }} {{ __('ui.account.wallet_credits') }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="rounded-2xl border border-gold/20 bg-gold/8 p-5" x-show="selectedPlan">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-lg font-semibold text-white" x-text="selectedPlan?.name"></p>
                                    <p class="mt-2 text-sm leading-6 text-silver" x-text="selectedPlan?.description"></p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-2xl font-bold text-gold" x-text="Number(selectedPlan?.priceCredits ?? 0).toLocaleString()"></p>
                                    <p class="text-xs uppercase tracking-[0.15em] text-steel" x-text="`${selectedPlan?.durationDays ?? 0} {{ __('ui.account.vip_days') }}`"></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-white/8 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-steel">
                                {{ __('ui.account.vip_balance', ['balance' => number_format($balance)]) }}
                            </p>
                            <button type="submit" class="button button-primary" @disabled(! $gameWritesEnabled || $plans === [] || $characters === [])>
                                {{ __('ui.account.vip_review_purchase') }}
                            </button>
                        </div>

                        @if (! $gameWritesEnabled)
                            <p class="text-sm text-amber-200">{{ __('ui.account.vip_writes_disabled') }}</p>
                        @endif
                    </form>

                    <aside class="rounded-2xl border border-white/8 bg-black/20 p-5">
                        <p class="kicker">{{ __('ui.account.vip_active_characters') }}</p>
                        <div class="mt-4 grid gap-3">
                            @forelse ($entitlements as $entitlement)
                                <div class="rounded-xl border border-white/8 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <strong class="text-white">{{ $entitlement->character_name }}</strong>
                                        <span @class([
                                            'text-xs font-semibold uppercase tracking-[0.12em]',
                                            'text-emerald-300' => $entitlement->sync_status === \App\Vip\VipSyncStatus::Synced,
                                            'text-amber-200' => $entitlement->sync_status !== \App\Vip\VipSyncStatus::Synced,
                                        ])>{{ __('ui.account.vip_sync_'.$entitlement->sync_status->value) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-steel">{{ __('ui.account.vip_until', ['date' => $entitlement->expires_at->format('Y-m-d H:i')]) }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-steel">{{ __('ui.account.vip_none') }}</p>
                            @endforelse
                        </div>
                    </aside>
                </div>

                @if ($purchases->isNotEmpty())
                    <div class="mt-6 overflow-x-auto rounded-2xl border border-white/8">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-black/20 text-xs uppercase tracking-[0.14em] text-steel">
                                <tr>
                                    <th class="px-4 py-3">{{ __('ui.account.vip_character') }}</th>
                                    <th class="px-4 py-3">{{ __('ui.account.vip_plan') }}</th>
                                    <th class="px-4 py-3">{{ __('ui.account.vip_expires') }}</th>
                                    <th class="px-4 py-3">{{ __('ui.account.vip_price') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/6">
                                @foreach ($purchases as $purchase)
                                    <tr>
                                        <td class="px-4 py-3 text-white">{{ $purchase->character_name }}</td>
                                        <td class="px-4 py-3 text-silver">{{ $purchase->plan_name }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-steel">{{ $purchase->expires_at->format('Y-m-d H:i') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-gold">{{ number_format($purchase->price_credits) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div x-cloak x-show="confirmationOpen" class="fixed inset-0 z-[110] grid place-items-center bg-black/85 p-4" @click.self="confirmationOpen = false">
                    <section class="panel-card w-full max-w-lg p-6" role="alertdialog" aria-modal="true">
                        <p class="kicker">{{ __('ui.account.vip_confirm') }}</p>
                        <h3 class="mt-3 text-2xl font-semibold text-white" x-text="selectedCharacter"></h3>
                        <p class="mt-4 text-silver">{{ __('ui.account.vip_confirm_help') }}</p>
                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" class="button button-ghost" @click="confirmationOpen = false">{{ __('ui.account.credentials_cancel') }}</button>
                            <button type="button" class="button button-primary" @click="$refs.vipPurchaseForm.submit()">{{ __('ui.account.vip_buy') }}</button>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </template>
</div>
