<?php

namespace Tests\Unit;

use App\Support\Catalog\FilterUrlBuilder;
use PHPUnit\Framework\TestCase;

class FilterUrlBuilderTest extends TestCase
{
    public function test_it_builds_canonical_filter_url_with_sorted_unique_segments(): void
    {
        $builder = new FilterUrlBuilder;

        $url = $builder->build('kaptsi', [
            'material' => 'shtuchne-hutro',
            'kolir' => ['bilyi', 'bezhevyi', 'bilyi'],
        ]);

        $this->assertSame(
            '/catalog/kaptsi/filter/kolir/bezhevyi/kolir/bilyi/material/shtuchne-hutro',
            $url,
        );
    }

    public function test_it_parses_filter_segments_from_url_path(): void
    {
        $builder = new FilterUrlBuilder;

        $filters = $builder->parse('/catalog/kaptsi/filter/material/shtuchne-hutro/kolir/bilyi?page=2');

        $this->assertSame([
            'kolir' => ['bilyi'],
            'material' => ['shtuchne-hutro'],
        ], $filters);
    }

    public function test_it_returns_category_path_when_filters_are_empty(): void
    {
        $builder = new FilterUrlBuilder;

        $this->assertSame('/catalog/kaptsi', $builder->build('/catalog/kaptsi', []));
    }

    public function test_it_normalizes_ukrainian_filter_slugs_consistently(): void
    {
        $builder = new FilterUrlBuilder;

        $this->assertSame(
            '/catalog/kaptsi/filter/material/eko-shkira',
            $builder->build('Капці', ['Матеріал' => ['Еко-шкіра']]),
        );
    }
}
