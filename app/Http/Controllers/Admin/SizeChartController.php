<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSizeChartRequest;
use App\Http\Requests\Admin\UpdateSizeChartRequest;
use App\Models\SizeChart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SizeChartController extends Controller
{
    public function index(): Response
    {
        $charts = SizeChart::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SizeChart $chart): array => [
                'id' => $chart->id,
                'title' => $chart->title,
                'code' => $chart->code,
                'description' => $chart->description,
                'is_active' => $chart->is_active,
                'sort_order' => $chart->sort_order,
                'products_count' => $chart->products_count,
                'content_json' => $chart->content_json,
            ]);

        return Inertia::render('Admin/Catalog/SizeCharts/Index', [
            'charts' => $charts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/SizeCharts/Form', [
            'mode' => 'create',
            'chart' => [
                'title' => '',
                'code' => '',
                'description' => '',
                'content_json' => [
                    'columns' => ['Розмір', 'Довжина стопи, см'],
                    'rows' => [['', '']],
                ],
                'content_html' => '',
                'image_path' => '',
                'is_active' => true,
                'sort_order' => 0,
            ],
        ]);
    }

    public function store(StoreSizeChartRequest $request): RedirectResponse
    {
        $data = $request->validated();

        SizeChart::query()->create([
            'title' => $data['title'],
            'code' => $this->resolveCode($data['code'] ?? null, $data['title']),
            'description' => $data['description'] ?? null,
            'content_json' => $this->normalizeContent($data['content_json'] ?? null),
            'content_html' => $data['content_html'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.size-charts.index')
            ->with('success', 'Розмірну сітку створено');
    }

    public function edit(SizeChart $size_chart): Response
    {
        return Inertia::render('Admin/Catalog/SizeCharts/Form', [
            'mode' => 'edit',
            'chart' => [
                'id' => $size_chart->id,
                'title' => $size_chart->title,
                'code' => $size_chart->code,
                'description' => $size_chart->description,
                'content_json' => $size_chart->content_json ?: [
                    'columns' => ['Розмір', 'Довжина стопи, см'],
                    'rows' => [['', '']],
                ],
                'content_html' => $size_chart->content_html,
                'image_path' => $size_chart->image_path,
                'is_active' => $size_chart->is_active,
                'sort_order' => $size_chart->sort_order,
            ],
        ]);
    }

    public function update(UpdateSizeChartRequest $request, SizeChart $size_chart): RedirectResponse
    {
        $data = $request->validated();

        $size_chart->update([
            'title' => $data['title'],
            'code' => $this->resolveCode($data['code'] ?? null, $data['title'], $size_chart->id),
            'description' => $data['description'] ?? null,
            'content_json' => $this->normalizeContent($data['content_json'] ?? null),
            'content_html' => $data['content_html'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('admin.size-charts.index')
            ->with('success', 'Розмірну сітку оновлено');
    }

    public function destroy(SizeChart $size_chart): RedirectResponse
    {
        $size_chart->delete();

        return redirect()
            ->route('admin.size-charts.index')
            ->with('success', 'Розмірну сітку видалено');
    }

    private function normalizeContent(?array $content): ?array
    {
        if (! $content) {
            return null;
        }

        $columns = collect($content['columns'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $rows = collect($content['rows'] ?? [])
            ->map(fn ($row) => collect($row)->map(fn ($value) => trim((string) $value))->values()->all())
            ->filter(fn ($row) => collect($row)->filter()->isNotEmpty())
            ->values();

        if ($columns->isEmpty() && $rows->isEmpty()) {
            return null;
        }

        return [
            'columns' => $columns->all(),
            'rows' => $rows->all(),
        ];
    }

    private function resolveCode(?string $code, string $title, ?int $ignoreId = null): string
    {
        $base = trim((string) $code);
        $base = $base !== '' ? $base : Str::slug($title, '_');
        $base = $base !== '' ? $base : 'size_chart';
        $candidate = $base;
        $counter = 1;

        while (SizeChart::query()
            ->where('code', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'_'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
