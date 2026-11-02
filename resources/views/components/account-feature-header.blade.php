@props([
    'kicker',
    'heading',
    'intro',
    'backRoute' => 'account.dashboard',
    'backLabel' => null,
])

<header {{ $attributes->class('flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between') }}>
    <div>
        <div class="eyebrow mb-4">{{ $kicker }}</div>
        <h1 class="font-display text-4xl text-white sm:text-5xl">{{ $heading }}</h1>
        <p class="mt-3 max-w-3xl text-silver">{{ $intro }}</p>
    </div>
    <a href="{{ route($backRoute) }}" class="button button-ghost">
        {{ $backLabel ?? __('ui.web_storage.back_to_account') }}
    </a>
</header>
