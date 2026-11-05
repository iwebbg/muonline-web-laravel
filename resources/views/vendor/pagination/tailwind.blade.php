@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="flex items-center justify-between gap-3 sm:hidden">
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
        </div>

        <div class="hidden items-center justify-between gap-5 sm:flex">
            <p class="text-sm text-steel">
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-semibold text-silver">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-semibold text-silver">{{ $paginator->lastItem() }}</span>
                @else
                    <span class="font-semibold text-silver">{{ $paginator->count() }}</span>
                @endif
                {!! __('of') !!}
                <span class="font-semibold text-silver">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </p>

            <span class="isolate inline-flex overflow-hidden rounded-xl border border-white/10 bg-black/25 shadow-lg shadow-black/20 rtl:flex-row-reverse">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span class="grid size-10 cursor-not-allowed place-items-center border-r border-white/8 text-steel/40 rtl:border-l rtl:border-r-0" aria-hidden="true">
                            <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        rel="prev"
                        class="grid size-10 place-items-center border-r border-white/8 text-steel transition hover:bg-white/[0.05] hover:text-gold focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-gold rtl:border-l rtl:border-r-0"
                        aria-label="{{ __('pagination.previous') }}"
                    >
                        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true">
                            <span class="grid h-10 min-w-10 cursor-default place-items-center border-r border-white/8 px-3 text-sm text-steel rtl:border-l rtl:border-r-0">
                                {{ $element }}
                            </span>
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="grid h-10 min-w-10 cursor-default place-items-center border-r border-gold/20 bg-gold/10 px-3 text-sm font-bold text-gold rtl:border-l rtl:border-r-0">
                                        {{ $page }}
                                    </span>
                                </span>
                            @else
                                <a
                                    href="{{ $url }}"
                                    class="grid h-10 min-w-10 place-items-center border-r border-white/8 px-3 text-sm font-semibold text-silver transition hover:bg-white/[0.05] hover:text-gold focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-gold rtl:border-l rtl:border-r-0"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                >
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        rel="next"
                        class="grid size-10 place-items-center text-steel transition hover:bg-white/[0.05] hover:text-gold focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-gold"
                        aria-label="{{ __('pagination.next') }}"
                    >
                        <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span class="grid size-10 cursor-not-allowed place-items-center text-steel/40" aria-hidden="true">
                            <svg class="size-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif
