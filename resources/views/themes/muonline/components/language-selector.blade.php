@props(['align' => 'right'])

@php
    $supportedLanguages = config('localization.supported', []);
    $currentLocale = app()->getLocale();
    $currentLanguage = $supportedLanguages[$currentLocale] ?? [
        'label' => strtoupper($currentLocale),
        'name' => strtoupper($currentLocale),
    ];
@endphp

<div
    {{ $attributes->class('language-selector') }}
    x-data="{ languageMenuOpen: false }"
    @click.outside="languageMenuOpen = false"
    @keydown.escape.window="languageMenuOpen = false"
>
    <button
        type="button"
        class="language-selector-trigger"
        aria-haspopup="menu"
        :aria-expanded="languageMenuOpen"
        aria-label="{{ __('ui.language.label') }}: {{ $currentLanguage['name'] }}"
        @click="languageMenuOpen = !languageMenuOpen"
    >
        <span>{{ $currentLanguage['label'] }}</span>
        <svg
            viewBox="0 0 20 20"
            class="size-3.5 transition-transform"
            :class="languageMenuOpen ? 'rotate-180' : ''"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path d="m5 7.5 5 5 5-5" />
        </svg>
    </button>

    <div
        class="language-selector-menu {{ $align === 'left' ? 'left-0' : 'right-0' }}"
        role="menu"
        aria-label="{{ __('ui.language.label') }}"
        x-show="languageMenuOpen"
        x-transition.origin.top
        x-cloak
    >
        @foreach ($supportedLanguages as $locale => $language)
            <form method="POST" action="{{ route('locale.update', $locale) }}" role="none">
                @csrf
                <button
                    type="submit"
                    class="language-selector-option {{ app()->isLocale($locale) ? 'language-selector-option-active' : '' }}"
                    role="menuitem"
                    @if (app()->isLocale($locale)) aria-current="true" @endif
                >
                    <span class="language-selector-code">{{ $language['label'] }}</span>
                    <span class="min-w-0 flex-1 truncate text-left">{{ $language['name'] }}</span>
                    @if (app()->isLocale($locale))
                        <span class="text-gold" aria-hidden="true">✓</span>
                    @endif
                </button>
            </form>
        @endforeach
    </div>
</div>
