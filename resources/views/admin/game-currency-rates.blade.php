@extends('layouts.admin')

@section('title', __('ui.native_services.rates_title'))

@section('content')
    <div class="admin-shell py-10 lg:py-14">
        <h1 class="font-display text-4xl text-white">{{ __('ui.native_services.rates_title') }}</h1>
        <p class="mt-3 max-w-3xl text-silver">{{ __('ui.native_services.rate_admin_help') }}</p>
        @if (session('status'))<p class="panel-card mt-5 p-4 text-emerald-200" role="status">{{ session('status') }}</p>@endif
        @if ($errors->any())<p class="panel-card mt-5 p-4 text-red-200" role="alert">{{ $errors->first() }}</p>@endif
        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            @foreach ($rates as $rate)
                <form method="POST" action="{{ route('admin.game-currency-rates.update', $rate) }}" class="panel-card grid gap-4 p-5">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="revision" value="{{ $rate->revision }}">
                    <h2 class="text-lg font-semibold text-white">{{ $rate->currency->label() }}</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="field-group"><span>Credits</span><input type="number" name="credit_units" min="1" max="1000000000" value="{{ $rate->credit_units }}" required></label>
                        <label class="field-group"><span>{{ $rate->currency->label() }}</span><input type="number" name="coin_units" min="1" max="1000000000" value="{{ $rate->coin_units }}" required></label>
                        <label class="field-group"><span>{{ __('ui.native_services.minimum_units') }}</span><input type="number" name="minimum_units" min="1" max="1000000" value="{{ $rate->minimum_units }}" required></label>
                        <label class="field-group"><span>{{ __('ui.native_services.maximum_units') }}</span><input type="number" name="maximum_units" min="1" max="1000000" value="{{ $rate->maximum_units }}" required></label>
                    </div>
                    <label class="flex items-center gap-3 text-sm text-silver"><input type="checkbox" name="enabled" value="1" @checked($rate->enabled)>{{ __('ui.native_services.enabled') }}</label>
                    <button type="submit" class="button button-primary">{{ __('ui.native_services.save_rate') }}</button>
                </form>
            @endforeach
        </div>
        <section class="panel-card mt-8 overflow-x-auto p-5">
            <h2 class="text-xl font-semibold text-white">{{ __('ui.native_services.history') }}</h2>
            <table class="mt-4 w-full text-left text-sm"><thead><tr class="text-steel"><th class="p-2">{{ __('ui.native_services.account') }}</th><th class="p-2">{{ __('ui.native_services.service') }}</th><th class="p-2">Credits</th><th class="p-2">{{ __('ui.native_services.status') }}</th></tr></thead><tbody>
                @foreach ($purchases as $purchase)
                    <tr class="border-t border-white/8 text-silver"><td class="p-2">{{ $purchase->account_id }}</td><td class="p-2">{{ __('ui.native_services.kinds.'.$purchase->kind->value) }}<x-native-account-purchase-summary :purchase="$purchase" /></td><td class="p-2">{{ number_format($purchase->credit_cost) }}</td><td class="p-2">{{ __('ui.native_services.states.'.$purchase->status->value) }}</td></tr>
                @endforeach
            </tbody></table>
        </section>
        <section class="mt-8 rounded-2xl border border-emerald-300/15 bg-emerald-300/6 p-4 text-sm leading-6 text-emerald-100">
            <strong class="block text-emerald-200">{{ __('ui.admin.active_configuration') }}</strong>
            {{ __('ui.admin.active_configuration_help') }}
        </section>
        <div class="mt-6">
            <x-admin-revision-history
                :revisions="$recentRevisions"
                :title="__('ui.admin.revision_history')"
                :empty-message="__('ui.admin.no_revisions')"
                wide
            />
        </div>
    </div>
@endsection
