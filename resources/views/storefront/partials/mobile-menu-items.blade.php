@foreach ($items as $item)
    @php
        $children = $item['children'] ?? [];
        $target = $item['target'] ?? '_self';
    @endphp

    <div class="storefront-mobile-nav__item is-level-{{ $level }}">
        @if (count($children) > 0)
            <button type="button" data-mobile-submenu-toggle aria-expanded="false">
                <span>{{ $item['title'] }}</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </button>

            <div class="storefront-mobile-nav__children">
                @include('storefront.partials.mobile-menu-items', ['items' => $children, 'level' => $level + 1])
            </div>
        @else
            <a
                href="{{ $item['url'] }}"
                target="{{ $target }}"
                @if ($target === '_blank') rel="noopener" @endif
            >
                {{ $item['title'] }}
            </a>
        @endif
    </div>
@endforeach
