@if ($paginator->hasPages())
    <nav class="abs-admin-pagination" role="navigation" aria-label="Results pagination">
        <p>Showing <b>{{ number_format($paginator->firstItem()) }}</b>–<b>{{ number_format($paginator->lastItem()) }}</b> of <b>{{ number_format($paginator->total()) }}</b></p>
        <div class="abs-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="disabled" aria-disabled="true">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="disabled">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
