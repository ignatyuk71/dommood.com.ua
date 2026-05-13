@foreach ($items as $item)
    @php
        $children = $item['children'] ?? [];
        $hasChildren = count($children) > 0;
        $level = $level ?? 0;
        $target = $item['target'] ?? '_self';
    @endphp

    <div class="storefront-desktop-menu__item is-level-{{ $level }}{{ $hasChildren ? ' has-children' : '' }}">
        <a
            href="{{ $item['url'] }}"
            target="{{ $target }}"
            @if ($target === '_blank') rel="noopener" @endif
            @if ($hasChildren) data-desktop-submenu-toggle aria-haspopup="true" aria-expanded="false" @endif
        >
            <span>{{ $item['title'] }}</span>
            @if ($hasChildren)
                <svg class="storefront-desktop-menu__caret" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            @endif
        </a>

        @if ($hasChildren)
            <div class="storefront-desktop-menu__submenu">
                @include('storefront.partials.desktop-menu-items', ['items' => $children, 'level' => $level + 1])
            </div>
        @endif
    </div>
@endforeach
