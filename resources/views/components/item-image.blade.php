@props([
    'src' => null,
    'alt' => '',
    'sourceExpression' => null,
    'labelExpression' => null,
])

<img
    src="{{ $src ?: asset('images/items/unknown-item.svg') }}"
    alt="{{ $alt }}"
    data-fallback-src="{{ asset('images/items/unknown-item.svg') }}"
    x-init="if ($el.complete && $el.naturalWidth === 0 && $el.getAttribute('src') !== $el.dataset.fallbackSrc) { $el.src = $el.dataset.fallbackSrc; }"
    @if ($sourceExpression !== null)
        x-bind:src="({{ $sourceExpression }}) || $el.dataset.fallbackSrc"
    @endif
    @if ($labelExpression !== null)
        x-bind:alt="{{ $labelExpression }}"
    @endif
    x-on:error="if ($el.getAttribute('src') !== $el.dataset.fallbackSrc) { $el.src = $el.dataset.fallbackSrc; }"
    {{ $attributes->merge(['loading' => 'lazy', 'class' => 'object-contain']) }}
>
