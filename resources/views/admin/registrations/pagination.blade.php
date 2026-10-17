@if($paginator->hasPages())
<nav class="pagination" aria-label="Registration pagination">
    @if($paginator->onFirstPage())<span aria-disabled="true">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>@endif
    @foreach($elements as $element)
        @if(is_string($element))<span>{{ $element }}</span>@else
            @foreach($element as $page => $url)
                @if($page === $paginator->currentPage())<span aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
    @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>@else<span aria-disabled="true">›</span>@endif
</nav>
@endif
