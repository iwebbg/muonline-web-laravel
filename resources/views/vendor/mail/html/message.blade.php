@props(['mailBranding'])

<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$mailBranding->homeUrl">
@if ($mailBranding->logoUrl !== null)
<img src="{{ $mailBranding->logoUrl }}" class="site-logo" alt="{{ $mailBranding->brandTitle }}">
@else
<table class="brand-lockup" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="brand-mark">MU</td>
<td class="brand-copy">
<span class="brand-title">{{ $mailBranding->brandTitle }}</span>
<span class="brand-subtitle">{{ $mailBranding->brandSubtitle }}</span>
</td>
</tr>
</table>
@endif
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $mailBranding->copyrightHolder }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
