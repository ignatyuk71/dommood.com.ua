<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\Media\ProductImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    private const PLACEMENT_IMAGE_SPECS = [
        'home_hero_main' => [
            'label' => 'Головна: великий банер',
            'desktop' => [2400, 1200],
            'mobile' => [1080, 1200],
            'desktop_note' => 'Hero кропиться в діапазоні ≈2:1-2.25:1: ключовий товар тримати правіше центру, лівий край чистий під текст.',
            'mobile_note' => 'Mobile hero близький до 9:10 на телефонах: головний обʼєкт по центру, без тексту на фото.',
        ],
        'home_hero_side_top' => [
            'label' => 'Головна: правий верхній',
            'desktop' => [1200, 720],
            'mobile' => [1200, 560],
            'desktop_note' => 'Side-банер кропиться близько 5:3-16:9: фокус по центру, нижній край не перевантажувати.',
            'mobile_note' => 'На телефоні цей слот лишається широким ≈2:1, тому не використовуйте вертикальні креативи.',
        ],
        'home_hero_side_bottom' => [
            'label' => 'Головна: правий нижній',
            'desktop' => [1200, 720],
            'mobile' => [1200, 560],
            'desktop_note' => 'Side-банер кропиться близько 5:3-16:9: фокус по центру, нижній край не перевантажувати.',
            'mobile_note' => 'На телефоні цей слот лишається широким ≈2:1, тому не використовуйте вертикальні креативи.',
        ],
    ];

    public function __construct(private readonly ProductImageOptimizer $imageOptimizer)
    {
    }

    public function index(): Response
    {
        $banners = Banner::query()
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Banner $banner): array => $this->serializeBanner($banner));

        return Inertia::render('Admin/Content/Banners/Index', [
            'banners' => $banners,
            'placementOptions' => $this->placementOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $banner = Banner::query()->create($this->payload($data));

        if ($request->hasFile('image')) {
            $banner->update([
                'image_path' => $this->storeImage($banner, $request->file('image'), 'desktop'),
            ]);
        }

        if ($request->hasFile('mobile_image')) {
            $banner->update([
                'mobile_image_path' => $this->storeImage($banner, $request->file('mobile_image'), 'mobile'),
            ]);
        }

        return redirect()
            ->route('admin.banners.index')
            ->with('success', 'Банер створено');
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validated($request, $banner);
        $oldImagePath = $banner->image_path;
        $oldMobileImagePath = $banner->mobile_image_path;

        $banner->update($this->payload($data, $banner));

        if ($request->boolean('delete_image')) {
            $this->deleteImage($oldImagePath);
            $banner->update(['image_path' => '']);
        }

        if ($request->boolean('delete_mobile_image')) {
            $this->deleteImage($oldMobileImagePath);
            $banner->update(['mobile_image_path' => null]);
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($oldImagePath);
            $banner->update([
                'image_path' => $this->storeImage($banner->refresh(), $request->file('image'), 'desktop'),
            ]);
        }

        if ($request->hasFile('mobile_image')) {
            $this->deleteImage($oldMobileImagePath);
            $banner->update([
                'mobile_image_path' => $this->storeImage($banner->refresh(), $request->file('mobile_image'), 'mobile'),
            ]);
        }

        return redirect()
            ->route('admin.banners.index')
            ->with('success', 'Банер оновлено');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $imagePath = $banner->image_path;
        $mobileImagePath = $banner->mobile_image_path;

        $banner->delete();
        $this->deleteImage($imagePath);
        $this->deleteImage($mobileImagePath);
        $this->deleteEmptyDirectory("banners/{$banner->id}");

        return redirect()
            ->route('admin.banners.index')
            ->with('success', 'Банер видалено');
    }

    private function validated(Request $request, ?Banner $banner = null): array
    {
        $isUpdate = $banner !== null;

        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'placement' => ['required', Rule::in(array_column($this->placementOptions(), 'value'))],
            'url' => ['nullable', 'string', 'max:500'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'image' => [$isUpdate ? 'nullable' : 'required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:6144'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:6144'],
            'delete_image' => ['boolean'],
            'delete_mobile_image' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }

    private function payload(array $data, ?Banner $banner = null): array
    {
        return [
            'title' => $data['title'],
            'placement' => $data['placement'],
            'url' => $this->nullableString($data['url'] ?? null),
            'button_text' => $this->nullableString($data['button_text'] ?? null),
            'image_path' => $banner?->image_path ?: '',
            'mobile_image_path' => $banner?->mobile_image_path,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    private function serializeBanner(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'placement' => $banner->placement,
            'placement_label' => $this->placementLabel($banner->placement),
            'image_path' => $banner->image_path,
            'image_url' => $this->imageUrl($banner->image_path),
            'mobile_image_path' => $banner->mobile_image_path,
            'mobile_image_url' => $this->imageUrl($banner->mobile_image_path),
            'url' => $banner->url,
            'button_text' => $banner->button_text,
            'is_active' => $banner->is_active,
            'sort_order' => $banner->sort_order,
            'starts_at' => $banner->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $banner->ends_at?->format('Y-m-d\TH:i'),
        ];
    }

    private function placementOptions(): array
    {
        return collect(self::PLACEMENT_IMAGE_SPECS)
            ->map(fn (array $spec, string $value): array => [
                'value' => $value,
                'label' => $spec['label'],
                'desktop_size' => $this->imageSizeLabel($spec['desktop']),
                'mobile_size' => $this->imageSizeLabel($spec['mobile']),
                'desktop_note' => $spec['desktop_note'],
                'mobile_note' => $spec['mobile_note'],
            ])
            ->values()
            ->all();
    }

    private function placementLabel(string $placement): string
    {
        return collect($this->placementOptions())->firstWhere('value', $placement)['label'] ?? $placement;
    }

    private function storeImage(Banner $banner, UploadedFile $image, string $variant): string
    {
        [$maxWidth, $maxHeight] = $this->recommendedImageSize($banner->placement, $variant);
        $slug = Str::slug($banner->title) ?: 'banner';
        $filename = "{$slug}-{$variant}-".now()->format('Ymd-His');

        return $this->imageOptimizer->storeAsWebp(
            $image,
            "banners/{$banner->id}",
            $filename,
            $maxWidth,
            $maxHeight
        );
    }

    private function recommendedImageSize(string $placement, string $variant): array
    {
        $spec = self::PLACEMENT_IMAGE_SPECS[$placement] ?? self::PLACEMENT_IMAGE_SPECS['home_hero_main'];
        $imageSpec = $variant === 'mobile' ? $spec['mobile'] : $spec['desktop'];

        return [(int) $imageSpec[0], (int) $imageSpec[1]];
    }

    private function imageSizeLabel(array $size): string
    {
        return ((int) $size[0]).'×'.((int) $size[1]).' px';
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

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
