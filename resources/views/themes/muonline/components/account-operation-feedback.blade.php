@props([
    'statusSession',
    'errorKey',
    'writesEnabled',
    'writesDisabledMessage',
])

@if (session($statusSession))
    <div class="mt-6 rounded-2xl border border-emerald-300/25 bg-emerald-300/8 p-4 text-sm text-emerald-100" role="status">
        {{ session($statusSession) }}
    </div>
@endif

@if ($errors->has($errorKey))
    <div class="mt-6 rounded-2xl border border-red-300/25 bg-red-300/8 p-4 text-sm text-red-100" role="alert">
        {{ $errors->first($errorKey) }}
    </div>
@endif

@if (! $writesEnabled)
    <div class="mt-6 rounded-2xl border border-amber-300/20 bg-amber-300/8 p-4 text-sm text-amber-100">
        {{ $writesDisabledMessage }}
    </div>
@endif
