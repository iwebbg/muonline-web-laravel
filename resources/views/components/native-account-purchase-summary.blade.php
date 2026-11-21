@props(['purchase'])

<div class="mt-1 text-xs text-steel">
    @if ($purchase->kind === \App\Economy\NativePurchaseKind::CurrencyExchange)
        {{ number_format($purchase->mutation_snapshot['amount']) }} {{ \App\Economy\NativeCurrency::from($purchase->mutation_snapshot['currency'])->label() }}
    @else
        {{ __('ui.native_services.vip_terms', [
            'level' => $purchase->mutation_snapshot['vip_level'],
            'days' => $purchase->mutation_snapshot['duration_days'],
            'credits' => number_format($purchase->credit_cost),
        ]) }}
    @endif
    <time class="mt-1 block" datetime="{{ $purchase->created_at->toIso8601String() }}">{{ $purchase->created_at->copy()->utc()->format('Y-m-d H:i') }} UTC</time>
    @if ($purchase->failure_code !== null)
        <span class="mt-1 block text-red-200">{{ __('ui.native_services.errors.'.$purchase->failure_code) }}</span>
    @endif
</div>
