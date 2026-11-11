@props([
    'pixels' => [],
    'label' => null,
])

<span
    {{ $attributes->class(['guild-mark']) }}
    @if ($label !== null) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
>
    @foreach (array_slice($pixels, 0, 64) as $color)
        <span class="guild-mark-pixel" style="background-color: {{ $color }}"></span>
    @endforeach
</span>
