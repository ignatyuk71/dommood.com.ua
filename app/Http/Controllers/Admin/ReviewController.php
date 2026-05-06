<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReviewRequest;
use App\Http\Requests\Admin\UpdateReviewRequest;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $reviews = Review::query()
            ->with(['product:id,name,sku', 'moderator:id,name'])
            ->when(in_array($status, Review::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('author_name', 'like', "%{$search}%")
                        ->orWhere('author_email', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Review $review): array => $this->serializeReview($review));

        return Inertia::render('Admin/Catalog/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'statusOptions' => $this->statusOptions(),
            'statusCounts' => $this->statusCounts(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalog/Reviews/Form', [
            'mode' => 'create',
            'review' => $this->emptyReview(),
            'products' => $this->productOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $status = $data['status'];

        Review::query()->create([
            ...$this->reviewPayload($data),
            ...$this->moderationPayload($status, $request->user()?->id),
        ]);

        return redirect()
            ->route('admin.reviews.index', ['status' => $status])
            ->with('success', 'Відгук створено');
    }

    public function edit(Review $review): Response
    {
        $review->load(['product:id,name,sku', 'moderator:id,name', 'repliedBy:id,name']);

        return Inertia::render('Admin/Catalog/Reviews/Form', [
            'mode' => 'edit',
            'review' => $this->serializeReview($review, full: true),
            'products' => $this->productOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $data = $request->validated();
        $status = $data['status'];
        $adminReply = $this->nullableString($data['admin_reply'] ?? null);
        $replyChanged = $adminReply !== $review->admin_reply;

        $review->update([
            ...$this->reviewPayload($data),
            ...$this->moderationPayload($status, $request->user()?->id, $review),
            'replied_by' => $adminReply && $replyChanged ? $request->user()?->id : $review->replied_by,
            'replied_at' => $adminReply && $replyChanged ? now() : $review->replied_at,
        ]);

        return redirect()
            ->route('admin.reviews.index', ['status' => $status])
            ->with('success', 'Відгук оновлено');
    }

    public function approve(Request $request, Review $review): RedirectResponse
    {
        $review->update($this->moderationPayload(Review::STATUS_APPROVED, $request->user()?->id, $review));

        return back()->with('success', 'Відгук одобрено');
    }

    public function reject(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'moderation_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->update([
            ...$this->moderationPayload(Review::STATUS_REJECTED, $request->user()?->id, $review),
            'moderation_note' => $this->nullableString($data['moderation_note'] ?? null) ?: $review->moderation_note,
        ]);

        return back()->with('success', 'Відгук відхилено');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return redirect()
            ->route('admin.reviews.index')
            ->with('success', 'Відгук видалено');
    }

    private function reviewPayload(array $data): array
    {
        return [
            'product_id' => $data['product_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'author_name' => $data['author_name'],
            'author_email' => $this->nullableString($data['author_email'] ?? null),
            'author_phone' => $this->nullableString($data['author_phone'] ?? null),
            'rating' => (int) $data['rating'],
            'title' => $this->nullableString($data['title'] ?? null),
            'body' => $data['body'],
            'status' => $data['status'],
            'is_verified_buyer' => (bool) ($data['is_verified_buyer'] ?? false),
            'source' => $this->nullableString($data['source'] ?? null) ?: 'site',
            'moderation_note' => $this->nullableString($data['moderation_note'] ?? null),
            'admin_reply' => $this->nullableString($data['admin_reply'] ?? null),
        ];
    }

    private function moderationPayload(string $status, ?int $adminId, ?Review $review = null): array
    {
        if ($status === Review::STATUS_APPROVED) {
            return [
                'status' => $status,
                'published_at' => $review?->published_at ?: now(),
                'moderated_by' => $adminId,
                'moderated_at' => now(),
            ];
        }

        if ($status === Review::STATUS_REJECTED) {
            return [
                'status' => $status,
                'published_at' => null,
                'moderated_by' => $adminId,
                'moderated_at' => now(),
            ];
        }

        return [
            'status' => Review::STATUS_PENDING,
            'published_at' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ];
    }

    private function serializeReview(Review $review, bool $full = false): array
    {
        return [
            'id' => $review->id,
            'product_id' => $review->product_id,
            'product' => $review->product ? [
                'id' => $review->product->id,
                'name' => $review->product->name,
                'sku' => $review->product->sku,
            ] : null,
            'customer_id' => $review->customer_id,
            'author_name' => $review->author_name,
            'author_email' => $review->author_email,
            'author_phone' => $review->author_phone,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status,
            'status_label' => $this->statusLabels()[$review->status] ?? $review->status,
            'is_verified_buyer' => $review->is_verified_buyer,
            'source' => $review->source,
            'moderation_note' => $review->moderation_note,
            'admin_reply' => $review->admin_reply,
            'published_at' => $review->published_at?->toDateTimeString(),
            'moderated_at' => $review->moderated_at?->toDateTimeString(),
            'created_at' => $review->created_at?->toDateTimeString(),
            'moderator' => $review->moderator ? [
                'id' => $review->moderator->id,
                'name' => $review->moderator->name,
            ] : null,
            'replied_by' => $full && $review->repliedBy ? [
                'id' => $review->repliedBy->id,
                'name' => $review->repliedBy->name,
            ] : null,
        ];
    }

    private function emptyReview(): array
    {
        return [
            'product_id' => null,
            'customer_id' => null,
            'author_name' => '',
            'author_email' => '',
            'author_phone' => '',
            'rating' => 5,
            'title' => '',
            'body' => '',
            'status' => Review::STATUS_PENDING,
            'is_verified_buyer' => false,
            'source' => 'site',
            'moderation_note' => '',
            'admin_reply' => '',
        ];
    }

    private function productOptions(): array
    {
        return Product::query()
            ->select(['id', 'name', 'sku'])
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'label' => trim($product->name.($product->sku ? " ({$product->sku})" : '')),
            ])
            ->all();
    }

    private function statusOptions(): array
    {
        return collect($this->statusLabels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function statusLabels(): array
    {
        return [
            Review::STATUS_PENDING => 'Очікує модерації',
            Review::STATUS_APPROVED => 'Одобрено',
            Review::STATUS_REJECTED => 'Відхилено',
        ];
    }

    private function statusCounts(): array
    {
        $counts = Review::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'all' => array_sum($counts),
            Review::STATUS_PENDING => (int) ($counts[Review::STATUS_PENDING] ?? 0),
            Review::STATUS_APPROVED => (int) ($counts[Review::STATUS_APPROVED] ?? 0),
            Review::STATUS_REJECTED => (int) ($counts[Review::STATUS_REJECTED] ?? 0),
        ];
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
