<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSizeChartRequest;
use App\Http\Requests\Admin\UpdateSizeChartRequest;
use App\Models\SizeChart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->through(fn (SizeChart $chart): array => $this->serializeChart($chart));

        return Inertia::render('Admin/Catalog/SizeCharts/Index', [
            'charts' => $charts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/SizeCharts/Form', [
            'mode' => 'create',
            'chart' => $this->emptyChart(),
        ]);
    }

    public function store(StoreSizeChartRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $chart = SizeChart::query()->create([
            'title' => $data['title'],
            'code' => $this->resolveCode($data['code'] ?? null, $data['title']),
            'description' => $data['description'] ?? null,
            'content_json' => $this->normalizeContent($data['content_json'] ?? null),
            'content_html' => $data['content_html'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        if ($request->hasFile('image')) {
            $chart->update([
                'image_path' => $this->storeImage($chart, $request->file('image')),
            ]);
        }

        return redirect()
            ->route('admin.size-charts.index')
            ->with('success', 'Розмірну сітку створено');
    }

    public function edit(SizeChart $size_chart): Response
    {
        return Inertia::render('Admin/Catalog/SizeCharts/Form', [
            'mode' => 'edit',
            'chart' => $this->serializeChart($size_chart, full: true),
        ]);
    }

    public function update(UpdateSizeChartRequest $request, SizeChart $size_chart): RedirectResponse
    {
        $data = $request->validated();

        $oldImagePath = $size_chart->image_path;
        $payload = [
            'title' => $data['title'],
            'code' => $this->resolveCode($data['code'] ?? null, $data['title'], $size_chart->id),
            'description' => $data['description'] ?? null,
            'content_json' => $this->normalizeContent($data['content_json'] ?? null),
            'content_html' => $data['content_html'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($request->boolean('delete_image')) {
            $this->deleteImage($oldImagePath);
            $payload['image_path'] = null;
        }

        $size_chart->update($payload);

        if ($request->hasFile('image')) {
            $this->deleteImage($oldImagePath);
            $size_chart->update([
                'image_path' => $this->storeImage($size_chart->refresh(), $request->file('image')),
            ]);
        }

        return redirect()
            ->route('admin.size-charts.index')
            ->with('success', 'Розмірну сітку оновлено');
    }

    public function destroy(SizeChart $size_chart): RedirectResponse
    {
        $imagePath = $size_chart->image_path;

        $size_chart->delete();
        $this->deleteImage($imagePath);
        $this->deleteChartDirectory($size_chart);

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

    private function serializeChart(SizeChart $chart, bool $full = false): array
    {
        return [
            'id' => $chart->id,
            'title' => $chart->title,
            'code' => $chart->code,
            'description' => $chart->description,
            'content_json' => $chart->content_json ?: [
                'columns' => ['Розмір', 'Довжина стопи, см'],
                'rows' => [['', '']],
            ],
            'content_html' => $full ? $chart->content_html : null,
            'image_path' => $chart->image_path,
            'image_url' => $this->imageUrl($chart->image_path),
            'is_active' => $chart->is_active,
            'sort_order' => $chart->sort_order,
            'products_count' => $chart->products_count ?? 0,
        ];
    }

    private function emptyChart(): array
    {
        return [
            'title' => '',
            'code' => '',
            'description' => '',
            'content_json' => [
                'columns' => ['Розмір', 'Довжина стопи, см'],
                'rows' => [['', '']],
            ],
            'content_html' => '',
            'image_path' => '',
            'image_url' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    private function storeImage(SizeChart $chart, UploadedFile $image): string
    {
        $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');
        $codeSlug = Str::slug($chart->code) ?: 'size-chart';
        $siteSlug = Str::slug(config('app.name', 'dommood')) ?: 'dommood';
        $filename = "{$codeSlug}-{$siteSlug}-".now()->format('Ymd-His').".{$extension}";

        return $image->storeAs("size-charts/{$chart->id}", $filename, 'public');
    }

    private function deleteImage(?string $path): void
    {
        $path = $this->normalizeStoragePath($path);

        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
        $this->deleteEmptyDirectory(dirname($path));
    }

    private function deleteChartDirectory(SizeChart $chart): void
    {
        $this->deleteEmptyDirectory("size-charts/{$chart->id}");
    }

    private function deleteEmptyDirectory(string $directory): void
    {
        $directory = trim($directory, './');

        if ($directory !== '' && Storage::disk('public')->exists($directory) && count(Storage::disk('public')->files($directory)) === 0) {
            Storage::disk('public')->deleteDirectory($directory);
        }
    }

    private function imageUrl(?string $path): ?string
    {
        $path = $this->normalizeStoragePath($path);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        return Str::of($path)
            ->replace('\\', '/')
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->ltrim('/')
            ->toString();
    }
}
