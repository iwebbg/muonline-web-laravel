@extends('layouts.app')

@section('title', 'Revolut Tag')

@section('content')
    <section class="shell max-w-4xl py-14">
        <div class="panel-card p-6 sm:p-9">
            <p class="eyebrow">Revolut Tag</p>
            <h1 class="mt-3 font-display text-4xl text-white">{{ __('ui.revolut_tag.heading') }}</h1>
            @if ($payment->credited_at !== null)
                <p class="mt-6 text-emerald-200">{{ __('ui.revolut_tag.confirmed') }}</p>
            @elseif ($payment->status === \App\Payments\CreditPaymentStatus::Pending)
                <p class="mt-5 leading-7 text-silver">{{ __('ui.revolut_tag.instructions', ['amount' => number_format($payment->amount_minor / 100, 2), 'currency' => $payment->currency, 'tag' => $payment->provider_payload['recipient_tag'], 'account' => $payment->account_id, 'credits' => number_format($payment->credits)]) }}</p>
                <dl class="mt-7 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-white/10 p-4"><dt class="text-sm text-steel">{{ __('ui.revolut_tag.amount') }}</dt><dd class="mt-2 text-xl text-gold">{{ number_format($payment->amount_minor / 100, 2) }} {{ $payment->currency }}</dd></div>
                    <div class="rounded-xl border border-white/10 p-4"><dt class="text-sm text-steel">{{ __('ui.revolut_tag.recipient') }}</dt><dd class="mt-2 break-all text-xl text-white">{{ $payment->provider_payload['recipient_tag'] }}</dd></div>
                    <div class="rounded-xl border border-white/10 p-4"><dt class="text-sm text-steel">{{ __('ui.revolut_tag.description') }}</dt><dd class="mt-2 break-all text-xl text-white">{{ $payment->account_id }}</dd></div>
                </dl>
                @if ($paymentUrl !== null)
                    <a href="{{ $paymentUrl }}" target="_blank" rel="noopener noreferrer" class="button button-primary mt-7">
                        {{ __('ui.revolut_tag.open_revolut') }}
                    </a>
                    <p class="mt-3 text-sm leading-6 text-silver">{{ __('ui.revolut_tag.check_details') }}</p>
                @endif
                <p class="mt-6 text-sm leading-6 text-steel">{{ __('ui.revolut_tag.pending_notice') }}</p>
            @else
                <p class="mt-6 text-silver">{{ __('ui.account.credits_statuses.'.$payment->status->value) }}</p>
            @endif
            <a href="{{ route('account.credits.index') }}" class="button button-ghost mt-7">{{ __('ui.revolut_tag.back') }}</a>
        </div>
    </section>
@endsection
