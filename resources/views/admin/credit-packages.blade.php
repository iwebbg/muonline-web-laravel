@extends('layouts.app')

@section('title', __('ui.admin.credit_packages_title'))

@section('content')
    <div class="shell py-10 lg:py-14">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="kicker">{{ __('ui.admin.economy') }}</p><h1 class="mt-3 font-display text-4xl text-white sm:text-5xl">{{ __('ui.admin.credit_packages_heading') }}</h1><p class="mt-3 max-w-3xl text-silver">{{ __('ui.admin.credit_packages_intro') }}</p></div>
            <div class="panel-card px-5 py-3 text-sm text-silver">{{ __('ui.admin.credit_providers') }}: {{ $enabledProviders === [] ? __('ui.admin.credit_none') : collect($enabledProviders)->map(fn ($provider) => ucfirst($provider->value))->join(', ') }}</div>
        </div>
        <div class="mt-8 grid gap-6 xl:grid-cols-[18rem_minmax(0,1fr)]">
            <x-admin-navigation active="credit-packages" />
            <div class="grid gap-6">
                <section class="panel-card p-6">
                    <p class="kicker">{{ __('ui.admin.credit_package_create') }}</p>
                    <form method="POST" action="{{ route('admin.credit-packages.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
                        <label class="field-group"><span>{{ __('ui.admin.credit_title_en') }}</span><input name="title_en" value="{{ old('title_en') }}" required maxlength="120"></label>
                        <label class="field-group"><span>{{ __('ui.admin.credit_title_bg') }}</span><input name="title_bg" value="{{ old('title_bg') }}" required maxlength="120"></label>
                        <label class="field-group"><span>{{ __('ui.admin.credit_amount') }}</span><input type="number" name="credits" value="{{ old('credits', 500) }}" min="1" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.credit_price') }}</span><input type="number" step="0.01" name="price" value="{{ old('price', '5.00') }}" min="0.01" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.credit_currency') }}</span><input name="currency" value="{{ old('currency', config('services.payments.currency')) }}" minlength="3" maxlength="3" required></label>
                        <label class="field-group"><span>{{ __('ui.admin.credit_status') }}</span><select name="status">@foreach ($statuses as $status)<option value="{{ $status->value }}">{{ __('ui.admin.credit_statuses.'.$status->value) }}</option>@endforeach</select></label>
                        <label class="field-group"><span>{{ __('ui.admin.credit_sort') }}</span><input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" required></label>
                        <div class="flex items-end justify-end"><button class="button button-primary">{{ __('ui.admin.credit_save') }}</button></div>
                    </form>
                </section>

                <section class="grid gap-4">
                    @forelse ($packages as $package)
                        <form method="POST" action="{{ route('admin.credit-packages.update', $package) }}" class="panel-card grid gap-4 p-5 md:grid-cols-2">@csrf @method('PUT')
                            <label class="field-group"><span>{{ __('ui.admin.credit_title_en') }}</span><input name="title_en" value="{{ $package->title_en }}" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_title_bg') }}</span><input name="title_bg" value="{{ $package->title_bg }}" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_amount') }}</span><input type="number" name="credits" value="{{ $package->credits }}" min="1" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_price') }}</span><input type="number" step="0.01" name="price" value="{{ number_format($package->price_minor / 100, 2, '.', '') }}" min="0.01" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_currency') }}</span><input name="currency" value="{{ $package->currency }}" minlength="3" maxlength="3" required></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_status') }}</span><select name="status">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected($package->status === $status)>{{ __('ui.admin.credit_statuses.'.$status->value) }}</option>@endforeach</select></label>
                            <label class="field-group"><span>{{ __('ui.admin.credit_sort') }}</span><input type="number" name="sort_order" value="{{ $package->sort_order }}" min="0" required></label>
                            <div class="flex items-end justify-end gap-3"><button class="button button-primary">{{ __('ui.admin.credit_save') }}</button></div>
                        </form>
                    @empty <div class="panel-card p-10 text-center text-steel">{{ __('ui.admin.credit_packages_empty') }}</div> @endforelse
                </section>

                <section class="panel-card overflow-x-auto p-0">
                    <div class="flex items-end justify-between gap-4 p-5"><div><p class="kicker">{{ __('ui.admin.credit_payment_history') }}</p></div><form method="GET"><select name="status" onchange="this.form.submit()"><option value="">{{ __('ui.admin.credit_all_statuses') }}</option>@foreach (\App\Payments\CreditPaymentStatus::cases() as $status)<option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></form></div>
                    <table class="min-w-full text-left text-sm"><thead class="border-y border-white/8 text-xs uppercase tracking-[0.14em] text-steel"><tr><th class="px-5 py-4">{{ __('ui.admin.wallet_account_id') }}</th><th class="px-5 py-4">{{ __('ui.admin.credit_provider') }}</th><th class="px-5 py-4">{{ __('ui.admin.credit_amount') }}</th><th class="px-5 py-4">{{ __('ui.admin.credit_price') }}</th><th class="px-5 py-4">{{ __('ui.admin.credit_status') }}</th><th class="px-5 py-4">{{ __('ui.admin.wallet_time') }}</th></tr></thead><tbody class="divide-y divide-white/6">@forelse ($payments as $payment)<tr><td class="px-5 py-4 text-white">{{ $payment->account_id }}</td><td class="px-5 py-4 text-silver">{{ ucfirst($payment->provider->value) }}</td><td class="px-5 py-4 text-gold">{{ number_format($payment->credits) }}</td><td class="px-5 py-4 text-silver">{{ number_format($payment->amount_minor / 100, 2) }} {{ $payment->currency }}</td><td class="px-5 py-4 text-silver">{{ ucfirst($payment->status->value) }}</td><td class="px-5 py-4 text-steel">{{ $payment->created_at?->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="6" class="px-5 py-10 text-center text-steel">{{ __('ui.admin.credit_payments_empty') }}</td></tr>@endforelse</tbody></table>
                    @if ($payments->hasPages())<div class="p-5">{{ $payments->links() }}</div>@endif
                </section>
            </div>
        </div>
    </div>
@endsection
