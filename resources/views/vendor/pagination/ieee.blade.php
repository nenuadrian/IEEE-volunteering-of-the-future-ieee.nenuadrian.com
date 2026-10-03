@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-sm text-warm-gray">
            Showing <span class="font-semibold text-ink">{{ $paginator->firstItem() }}</span>–<span class="font-semibold text-ink">{{ $paginator->lastItem() }}</span>
            of <span class="font-semibold text-ink">{{ $paginator->total() }}</span>
        </p>
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-md px-3 py-1.5 text-sm text-warm-gray/60">&lsaquo; Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-md px-3 py-1.5 text-sm text-brand hover:bg-brand-50">&lsaquo; Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-sm text-warm-gray">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="rounded-md bg-brand px-3 py-1.5 text-sm font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="rounded-md px-3 py-1.5 text-sm text-warmer-gray hover:bg-brand-50 hover:text-brand" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-md px-3 py-1.5 text-sm text-brand hover:bg-brand-50">Next &rsaquo;</a>
            @else
                <span class="rounded-md px-3 py-1.5 text-sm text-warm-gray/60">Next &rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
