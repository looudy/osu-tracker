{{-- Custom pagination view for the osu! Tracker.
     Uses the project's raw CSS (public/css/style.css) — NOT Tailwind.
     The framework default ships Tailwind/SVG markup whose size classes never load
     here, so the arrows render huge. This template uses plain text chevrons instead. --}}
@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Pagination Navigation">
        {{-- Previous page link --}}
        @if ($paginator->onFirstPage())
            <span class="pagination-link disabled" aria-disabled="true">&lsaquo; Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="pagination-link" rel="prev">&lsaquo; Prev</a>
        @endif

        {{-- Numbered page links --}}
        @foreach ($elements as $element)
            {{-- "..." separator --}}
            @if (is_string($element))
                <span class="pagination-link disabled" aria-disabled="true">{{ $element }}</span>
            @endif

            {{-- Array of page => url --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pagination-link active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="pagination-link">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next page link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="pagination-link" rel="next">Next &rsaquo;</a>
        @else
            <span class="pagination-link disabled" aria-disabled="true">Next &rsaquo;</span>
        @endif
    </nav>

    {{-- Result summary --}}
    @if ($paginator->total() > 0)
        <p class="pagination-summary">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }}
            of {{ $paginator->total() }} results
        </p>
    @endif
@endif
