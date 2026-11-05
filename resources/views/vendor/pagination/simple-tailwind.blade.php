@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span
                aria-disabled="true"
                class="inline-flex min-h-11 cursor-not-allowed items-center rounded-xl border border-white/8 bg-black/20 px-4 text-sm font-semibold text-steel/50"
            >
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a
                href="{{ $paginator->previousPageUrl() }}"
                rel="prev"
                class="inline-flex min-h-11 items-center rounded-xl border border-white/10 bg-black/20 px-4 text-sm font-semibold text-silver transition hover:border-gold/30 hover:bg-gold/[0.06] hover:text-gold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
            >
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a
                href="{{ $paginator->nextPageUrl() }}"
                rel="next"
                class="inline-flex min-h-11 items-center rounded-xl border border-white/10 bg-black/20 px-4 text-sm font-semibold text-silver transition hover:border-gold/30 hover:bg-gold/[0.06] hover:text-gold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold"
            >
                {!! __('pagination.next') !!}
            </a>
        @else
            <span
                aria-disabled="true"
                class="inline-flex min-h-11 cursor-not-allowed items-center rounded-xl border border-white/8 bg-black/20 px-4 text-sm font-semibold text-steel/50"
            >
                {!! __('pagination.next') !!}
            </span>
        @endif
    </nav>
@endif
