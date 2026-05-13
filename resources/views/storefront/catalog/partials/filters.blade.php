@php
    $filterMode = $filterMode ?? 'desktop';
    $isMobileFilters = $filterMode === 'mobile';
@endphp

<div class="storefront-catalog-filter-box">
    @unless ($isMobileFilters)
        <div class="storefront-catalog-filter-box__head">
            <strong>Фільтри</strong>
            @if (count($activeFilterLabels) > 0)
                <a href="{{ $clearFiltersUrl }}">Очистити</a>
            @endif
        </div>
    @endunless

    <div class="storefront-catalog-filter-box__groups">
        @if ($isMobileFilters)
            @if (count($categoryFilters) > 0)
                <details class="storefront-catalog-filter-accordion">
                    <summary>
                        <span>Категорії</span>
                    </summary>
                    <div class="storefront-catalog-filter-values storefront-catalog-filter-values--categories">
                        @foreach ($categoryFilters as $categoryFilter)
                            <a
                                href="{{ $categoryFilter['url'] }}"
                                @class([
                                    'storefront-catalog-filter-value',
                                    'is-active' => $categoryFilter['is_active'],
                                ])
                                @if ($categoryFilter['is_active']) aria-current="true" @endif
                            >
                                <span aria-hidden="true"></span>
                                <strong>{{ $categoryFilter['name'] }}</strong>
                                <em>{{ $categoryFilter['count'] }}</em>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif

            @foreach ($filterGroups as $group)
                <details class="storefront-catalog-filter-accordion">
                    <summary>
                        <span>{{ $group['name'] }}</span>
                    </summary>
                    <div @class([
                        'storefront-catalog-filter-values',
                        'storefront-catalog-filter-values--colors' => $group['display_type'] === 'color',
                    ])>
                        @foreach ($group['values'] as $value)
                            <a
                                href="{{ $value['url'] }}"
                                @class([
                                    'storefront-catalog-filter-value',
                                    'is-active' => $value['is_active'],
                                    'is-color' => $group['display_type'] === 'color',
                                ])
                                @if ($value['is_active']) aria-current="true" @endif
                            >
                                @if ($group['display_type'] === 'color')
                                    <span style="--dm-filter-color: {{ $value['color_hex'] ?: '#f1f3f7' }}"></span>
                                @else
                                    <span aria-hidden="true"></span>
                                @endif
                                <strong>{{ $value['value'] }}</strong>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        @endif

        @if (($priceFilter['is_available'] ?? false) === true)
            <section class="storefront-catalog-filter-group" aria-labelledby="filter-group-price">
                <h2 id="filter-group-price">Ціна, грн</h2>

                <form class="storefront-catalog-price-filter" action="{{ $priceFilter['action'] }}" method="get" data-catalog-price-filter data-catalog-price-filter-auto>
                    @foreach (($priceFilter['hidden_inputs'] ?? []) as $name => $value)
                        @if (is_array($value))
                            @foreach ($value as $item)
                                <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <div class="storefront-catalog-price-filter__ranges" aria-label="Діапазон ціни">
                        <input
                            type="range"
                            min="{{ $priceFilter['min'] }}"
                            max="{{ $priceFilter['max'] }}"
                            step="{{ $priceFilter['step'] }}"
                            value="{{ $priceFilter['from'] }}"
                            data-price-range-from
                            aria-label="Мінімальна ціна"
                        >
                        <input
                            type="range"
                            min="{{ $priceFilter['min'] }}"
                            max="{{ $priceFilter['max'] }}"
                            step="{{ $priceFilter['step'] }}"
                            value="{{ $priceFilter['to'] }}"
                            data-price-range-to
                            aria-label="Максимальна ціна"
                        >
                    </div>

                    <div class="storefront-catalog-price-filter__inputs">
                        <label>
                            <span>від</span>
                            <input type="number" name="price_from" min="{{ $priceFilter['min'] }}" max="{{ $priceFilter['max'] }}" value="{{ $priceFilter['from'] }}" inputmode="numeric" data-price-input-from>
                        </label>
                        <label>
                            <span>до</span>
                            <input type="number" name="price_to" min="{{ $priceFilter['min'] }}" max="{{ $priceFilter['max'] }}" value="{{ $priceFilter['to'] }}" inputmode="numeric" data-price-input-to>
                        </label>
                    </div>
                </form>
            </section>
        @endif

        @if (! $isMobileFilters && count($categoryFilters) > 0)
            <section class="storefront-catalog-filter-group" aria-labelledby="filter-group-categories">
                <h2 id="filter-group-categories">Категорії</h2>

                <div class="storefront-catalog-filter-values storefront-catalog-filter-values--categories">
                    @foreach ($categoryFilters as $categoryFilter)
                        <a
                            href="{{ $categoryFilter['url'] }}"
                            @class([
                                'storefront-catalog-filter-value',
                                'is-active' => $categoryFilter['is_active'],
                            ])
                            @if ($categoryFilter['is_active']) aria-current="true" @endif
                        >
                            <span aria-hidden="true"></span>
                            <strong>{{ $categoryFilter['name'] }}</strong>
                            <em>{{ $categoryFilter['count'] }}</em>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @foreach (! $isMobileFilters ? $filterGroups : [] as $group)
            <section class="storefront-catalog-filter-group" aria-labelledby="filter-group-{{ $group['slug'] }}">
                <h2 id="filter-group-{{ $group['slug'] }}">{{ $group['name'] }}</h2>

                <div @class([
                    'storefront-catalog-filter-values',
                    'storefront-catalog-filter-values--colors' => $group['display_type'] === 'color',
                ])>
                    @foreach ($group['values'] as $value)
                        <a
                            href="{{ $value['url'] }}"
                            @class([
                                'storefront-catalog-filter-value',
                                'is-active' => $value['is_active'],
                                'is-color' => $group['display_type'] === 'color',
                            ])
                            @if ($value['is_active']) aria-current="true" @endif
                        >
                            @if ($group['display_type'] === 'color')
                                <span style="--dm-filter-color: {{ $value['color_hex'] ?: '#f1f3f7' }}"></span>
                            @else
                                <span aria-hidden="true"></span>
                            @endif
                            <strong>{{ $value['value'] }}</strong>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($isMobileFilters)
            <button type="button" class="storefront-catalog-filter-show" data-catalog-filters-close>
                Показати {{ $totalProducts ?? 0 }} {{ $productsCountLabel ?? 'товарів' }}
            </button>
        @endif
    </div>
</div>
