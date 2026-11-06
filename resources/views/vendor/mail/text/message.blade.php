@props(['mailBranding'])

<x-mail::layout>
{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
{{ $subcopy }}
</x-slot:subcopy>
@endisset
</x-mail::layout>
