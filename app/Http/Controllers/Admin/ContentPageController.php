<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentPageRequest;
use App\Http\Requests\Admin\UpdateContentPageRequest;
use App\Models\ContentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $status = trim($request->string('status')->toString());

        $pages = ContentPage::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('meta_title', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['draft', 'published'], true), fn ($query) => $query->where('status', $status))
            ->orderByRaw("status = 'published' desc")
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ContentPage $page): array => $this->serializePage($page));

        return Inertia::render('Admin/Content/Pages/Index', [
            'pages' => $pages,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Content/Pages/Form', [
            'mode' => 'create',
            'page' => $this->emptyPage(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(StoreContentPageRequest $request): RedirectResponse
    {
        $page = ContentPage::query()->create($this->payload($request->validated()));

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', 'Сторінку створено');
    }

    public function edit(ContentPage $page): Response
    {
        return Inertia::render('Admin/Content/Pages/Form', [
            'mode' => 'edit',
            'page' => $this->serializePage($page, full: true),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateContentPageRequest $request, ContentPage $page): RedirectResponse
    {
        $page->update($this->payload($request->validated(), $page->id));

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('success', 'Сторінку оновлено');
    }

    public function destroy(ContentPage $page): RedirectResponse
    {
        $page->delete();

        return redirect()
            ->route('admin.pages.index')
            ->with('success', 'Сторінку видалено');
    }

    private function payload(array $data, ?int $ignoreId = null): array
    {
        $status = $data['status'] ?? 'draft';

        return [
            'title' => trim((string) $data['title']),
            'slug' => $this->resolveSlug($data['slug'] ?? null, $data['title'], $ignoreId),
            'content' => $this->nullableString($data['content'] ?? null),
            'status' => $status,
            'meta_title' => $this->nullableString($data['meta_title'] ?? null),
            'meta_description' => $this->nullableString($data['meta_description'] ?? null),
            'canonical_url' => $this->nullableString($data['canonical_url'] ?? null),
            'published_at' => $status === 'published' ? ($data['published_at'] ?? now()) : null,
        ];
    }

    private function resolveSlug(?string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) ($slug ?: $title))) ?: 'page';
        $candidate = $base;
        $counter = 1;

        while (ContentPage::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function serializePage(ContentPage $page, bool $full = false): array
    {
        $payload = [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'url' => url('/'.$page->slug),
            'status' => $page->status,
            'status_label' => $this->statusLabel($page->status),
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'canonical_url' => $page->canonical_url,
            'published_at' => $page->published_at?->format('Y-m-d\TH:i'),
            'updated_at' => $page->updated_at?->format('d.m.Y H:i'),
        ];

        if ($full) {
            $payload['content'] = $page->content;
        }

        return $payload;
    }

    private function emptyPage(): array
    {
        return [
            'id' => null,
            'title' => '',
            'slug' => '',
            'url' => null,
            'content' => '',
            'status' => 'draft',
            'meta_title' => '',
            'meta_description' => '',
            'canonical_url' => '',
            'published_at' => '',
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Чернетка'],
            ['value' => 'published', 'label' => 'Опубліковано'],
        ];
    }

    private function statusLabel(string $status): string
    {
        return collect($this->statusOptions())->firstWhere('value', $status)['label'] ?? $status;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
