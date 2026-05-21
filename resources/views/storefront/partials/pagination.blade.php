@if ($paginator->hasPages())
    <nav class="storefront-pagination" aria-label="Навігація сторінками каталогу">
        <div class="storefront-pagination__mobile">
            @if ($paginator->onFirstPage())
                <span class="storefront-pagination__control is-disabled" aria-disabled="true">Назад</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="storefront-pagination__control" rel="prev">Назад</a>
            @endif

            <span class="storefront-pagination__status">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="storefront-pagination__control" rel="next">Далі</a>
            @else
                <span class="storefront-pagination__control is-disabled" aria-disabled="true">Далі</span>
            @endif
        </div>

        <div class="storefront-pagination__desktop">
            @if ($paginator->onFirstPage())
                <span class="storefront-pagination__icon is-disabled" aria-disabled="true" aria-label="Попередня сторінка">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="storefront-pagination__icon" rel="prev" aria-label="Попередня сторінка">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="storefront-pagination__dots" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="storefront-pagination__page is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="storefront-pagination__page" aria-label="Сторінка {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="storefront-pagination__icon" rel="next" aria-label="Наступна сторінка">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                </a>
            @else
                <span class="storefront-pagination__icon is-disabled" aria-disabled="true" aria-label="Наступна сторінка">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
