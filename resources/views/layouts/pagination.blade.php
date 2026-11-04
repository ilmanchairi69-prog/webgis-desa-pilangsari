@if ($paginator->hasPages())
    <nav class="app-pagination" aria-label="Navigasi halaman">
        <p class="app-pagination-summary">
            Menampilkan <strong>{{ $paginator->firstItem() ?? 0 }}&ndash;{{ $paginator->lastItem() ?? 0 }}</strong>
            dari <strong>{{ $paginator->total() }}</strong> data
        </p>

        <ul class="app-pagination-pages">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="app-pagination-control" aria-disabled="true" aria-label="Halaman sebelumnya">
                @else
                    <a class="app-pagination-control" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">
                @endif
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m14 6-6 6 6 6" /></svg>
                @if ($paginator->onFirstPage())</span>@else</a>@endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="app-pagination-gap" aria-hidden="true">{{ $element }}</span></li>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="app-pagination-control" aria-current="page" aria-label="Halaman {{ $page }}">{{ $page }}</span>
                            @else
                                <a class="app-pagination-control" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a class="app-pagination-control" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">
                @else
                    <span class="app-pagination-control" aria-disabled="true" aria-label="Halaman berikutnya">
                @endif
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m10 6 6 6-6 6" /></svg>
                @if ($paginator->hasMorePages())</a>@else</span>@endif
            </li>
        </ul>
    </nav>
@endif
