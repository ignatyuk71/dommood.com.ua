@php
    $items = collect($items ?? [])
        ->map(function (array $item): array {
            $label = trim((string) ($item['label'] ?? $item['name'] ?? ''));

            return [
                'label' => $label,
                'url' => $item['url'] ?? null,
            ];
        })
        ->filter(fn (array $item): bool => $item['label'] !== '')
        ->values();
@endphp

@if ($items->isNotEmpty())
    <nav class="storefront-breadcrumbs" aria-label="Хлібні крихти">
        <ol class="storefront-breadcrumbs__list">
            @foreach ($items as $item)
                <li @class(['is-current' => $loop->last])>
                    @if (! $loop->last && filled($item['url']))
                        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    @else
                        <span @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
