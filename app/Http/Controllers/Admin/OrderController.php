<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\ProductImage;
use App\Services\AdminActivityLogger;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const ADMIN_TIMEZONE = 'Europe/Kyiv';

    private const STATUS_OPTIONS = [
        'new' => [
            'label' => 'Нове',
            'class' => 'bg-amber-50 text-amber-700',
        ],
        'confirmed' => [
            'label' => 'Підтверджено',
            'class' => 'bg-indigo-50 text-indigo-700',
        ],
        'processing' => [
            'label' => 'В роботі',
            'class' => 'bg-sky-50 text-sky-700',
        ],
        'shipped' => [
            'label' => 'Відправлено',
            'class' => 'bg-blue-50 text-blue-700',
        ],
        'completed' => [
            'label' => 'Завершено',
            'class' => 'bg-emerald-50 text-emerald-700',
        ],
        'cancelled' => [
            'label' => 'Скасовано',
            'class' => 'bg-red-50 text-red-700',
        ],
        'returned' => [
            'label' => 'Повернення',
            'class' => 'bg-rose-50 text-rose-700',
        ],
    ];

    private const STATUS_GROUPS = [
        'new' => ['new', 'awaiting_confirmation', 'pending_payment'],
        'in_work' => ['confirmed', 'processing', 'shipped'],
        'completed' => ['completed'],
        'returned' => ['returned', 'cancelled'],
    ];

    private const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'Не оплачено',
        'pending' => 'Очікує оплату',
        'paid' => 'Оплачено',
        'cod' => 'Оплата при отриманні',
        'refunded' => 'Повернено оплату',
        'failed' => 'Помилка оплати',
    ];

    private const PAYMENT_METHOD_LABELS = [
        'cod' => 'Оплата при отриманні',
        'cash_on_delivery' => 'Оплата при отриманні',
        'card' => 'Оплата карткою',
        'liqpay' => 'LiqPay',
        'monobank' => 'Monobank',
        'mono' => 'Monobank',
        'iban' => 'Оплата на рахунок',
    ];

    private const DELIVERY_METHOD_LABELS = [
        'nova_poshta_branch' => 'Нова пошта: відділення',
        'nova_poshta_postomat' => 'Нова пошта: поштомат',
        'nova_poshta_courier' => 'Нова пошта: курʼєр',
        'ukrposhta' => 'Укрпошта',
        'courier' => 'Курʼєр',
        'pickup' => 'Самовивіз',
    ];

    private const SOURCE_LABELS = [
        'meta' => 'Meta',
        'facebook' => 'Meta',
        'instagram' => 'Meta',
        'tiktok' => 'TikTok',
        'google' => 'Google',
    ];

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status_group' => $request->string('status_group')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $orders = Order::query()
            ->with([
                'items.product.images',
                'items.variant.images',
            ])
            ->withCount('items')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($inner) use ($search): void {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('delivery_city', 'like', "%{$search}%")
                        ->orWhere('delivery_branch', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($filters['status'], self::STATUS_OPTIONS), function ($query) use ($filters): void {
                $query->whereIn('status', $this->statusFilterValues($filters['status']));
            }, function ($query) use ($filters): void {
                if (isset(self::STATUS_GROUPS[$filters['status_group']])) {
                    $query->whereIn('status', self::STATUS_GROUPS[$filters['status_group']]);
                }
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $order): array => $this->serializeOrder($order));

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'statusGroups' => [
                ['value' => 'new', 'label' => 'Нові'],
                ['value' => 'in_work', 'label' => 'В роботі'],
                ['value' => 'completed', 'label' => 'Завершені'],
                ['value' => 'returned', 'label' => 'Повернення'],
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'items.product.images',
            'items.variant.images',
            'paymentTransactions',
        ]);

        $histories = $order->statusHistories()
            ->with('user')
            ->latest()
            ->get();

        return Inertia::render('Admin/Orders/Show', [
            'order' => $this->serializeOrderDetails($order, $histories),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUS_OPTIONS))],
        ]);

        $oldStatus = $order->status;
        $newStatus = $data['status'];
        $history = null;

        DB::transaction(function () use ($request, $order, $oldStatus, $newStatus, &$history): void {
            $timestamps = [];

            if ($newStatus === 'confirmed' && ! $order->confirmed_at) {
                $timestamps['confirmed_at'] = now();
            }

            if ($newStatus === 'completed' && ! $order->completed_at) {
                $timestamps['completed_at'] = now();
            }

            if (in_array($newStatus, ['cancelled', 'returned'], true) && ! $order->cancelled_at) {
                $timestamps['cancelled_at'] = now();
            }

            $order->update([
                'status' => $newStatus,
                ...$timestamps,
            ]);

            if ($oldStatus !== $newStatus) {
                $history = OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()?->id,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                ]);

                app(AdminActivityLogger::class)->log(
                    $request,
                    'order.status_updated',
                    $order,
                    oldValues: [
                        'status' => $oldStatus,
                    ],
                    newValues: [
                        'status' => $newStatus,
                    ],
                    description: 'Менеджер змінив статус замовлення',
                );
            }
        });

        $history?->load('user');

        return response()->json([
            'message' => 'Статус замовлення оновлено',
            'status' => $this->statusMeta($newStatus),
            'history' => $history ? $this->serializeHistory($history) : null,
        ]);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $oldValues = [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total_cents' => (int) $order->total_cents,
        ];

        $order->delete();

        app(AdminActivityLogger::class)->log(
            $request,
            'order.deleted',
            $order,
            oldValues: $oldValues,
            newValues: ['deleted' => true],
            description: 'Менеджер видалив замовлення',
        );

        return redirect()
            ->route('admin.orders.index')
            ->with('success', 'Замовлення видалено');
    }

    private function serializeOrder(Order $order): array
    {
        $items = $order->items->map(fn (OrderItem $item): array => $this->serializeItem($item, $order->currency))->values();
        $source = $this->sourceCode($order);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'created_at' => $this->adminDateTime($order->created_at),
            'status' => $this->normalizeOrderStatus($order->status),
            'status_meta' => $this->statusMeta($order->status),
            'payment_status' => $order->payment_status,
            'payment_status_label' => self::PAYMENT_STATUS_LABELS[$order->payment_status] ?? $order->payment_status,
            'payment_method' => $order->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($order->payment_method),
            'payment_provider' => $order->payment_provider,
            'payment_reference' => $order->payment_reference,
            'paid_at' => $this->adminDateTime($order->paid_at),
            'amount_due' => $this->formatMoney($this->amountDueCents($order), $order->currency),
            'payment_ui' => $this->paymentUi($order),
            'delivery_method' => $order->delivery_method,
            'delivery_method_label' => self::DELIVERY_METHOD_LABELS[$order->delivery_method] ?? ($order->delivery_method ?: 'Не вказано'),
            'delivery_provider' => $order->delivery_provider,
            'delivery_type' => $order->delivery_type,
            'delivery_city' => $order->delivery_city,
            'delivery_city_ref' => $order->delivery_city_ref,
            'delivery_address' => $order->delivery_address,
            'delivery_branch' => $order->delivery_branch,
            'delivery_branch_ref' => $order->delivery_branch_ref,
            'delivery_line' => $this->deliveryLine($order),
            'delivery_recipient_name' => $order->delivery_recipient_name,
            'delivery_recipient_phone' => $order->delivery_recipient_phone,
            'delivery_snapshot' => $order->delivery_snapshot ?? [],
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'currency' => $order->currency,
            'subtotal' => $this->formatMoney($order->subtotal_cents, $order->currency),
            'discount_total' => $this->formatMoney($order->discount_total_cents, $order->currency),
            'delivery_price' => $this->formatMoney($order->delivery_price_cents, $order->currency),
            'has_free_delivery' => $this->hasFreeDelivery($order),
            'total' => $this->formatMoney($order->total_cents, $order->currency),
            'total_cents' => $order->total_cents,
            'comment' => $order->comment,
            'manager_comment' => $order->manager_comment,
            'source' => [
                'code' => $source,
                'label' => self::SOURCE_LABELS[$source] ?? 'Інше',
                'class' => $this->sourceClass($source),
            ],
            'items_count' => $order->items_count ?? $items->count(),
            'thumbs' => $items->pluck('image_url')->filter()->take(3)->values(),
            'items' => $items,
        ];
    }

    private function serializeOrderDetails(Order $order, Collection $histories): array
    {
        return [
            ...$this->serializeOrder($order),
            'amount_due' => $this->formatMoney($this->amountDueCents($order), $order->currency),
            'items_quantity' => $order->items->sum('quantity'),
            'positions_count' => $order->items->count(),
            'tracking' => [
                'utm_source' => $order->utm_source,
                'utm_medium' => $order->utm_medium,
                'utm_campaign' => $order->utm_campaign,
                'utm_content' => $order->utm_content,
                'utm_term' => $order->utm_term,
                'landing_page_url' => $order->landing_page_url,
                'referrer_url' => $order->referrer_url,
            ],
            'status_histories' => $histories
                ->map(fn (OrderStatusHistory $history): array => $this->serializeHistory($history))
                ->values(),
            'payment_transactions' => $order->paymentTransactions
                ->sortByDesc('id')
                ->map(fn (PaymentTransaction $transaction): array => $this->serializePaymentTransaction($transaction, $order->currency))
                ->values(),
        ];
    }

    private function serializePaymentTransaction(PaymentTransaction $transaction, string $currency): array
    {
        return [
            'id' => $transaction->id,
            'provider' => $transaction->provider,
            'provider_transaction_id' => $transaction->provider_transaction_id,
            'external_order_id' => $transaction->external_order_id,
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'status_label' => $this->paymentTransactionStatusLabel($transaction->status),
            'status_class' => $this->paymentTransactionStatusClass($transaction->status),
            'amount' => $this->formatMoney($transaction->amount_cents, $transaction->currency ?: $currency),
            'is_test' => $transaction->is_test,
            'failure_reason' => $transaction->failure_reason,
            'processed_at' => $this->adminDateTime($transaction->processed_at),
            'paid_at' => $this->adminDateTime($transaction->paid_at),
            'created_at' => $this->adminDateTime($transaction->created_at),
        ];
    }

    private function serializeItem(OrderItem $item, ?string $currency = 'UAH'): array
    {
        $snapshot = $item->product_snapshot ?? [];

        return [
            'id' => $item->id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'sku' => $item->sku,
            'quantity' => $item->quantity,
            'price' => $this->formatMoney($item->price_cents, $currency),
            'total' => $this->formatMoney($item->total_cents, $currency),
            'image_url' => $this->itemImageUrl($item, $snapshot),
            'snapshot' => [
                'size' => $snapshot['size'] ?? null,
                'color' => $snapshot['color'] ?? null,
            ],
        ];
    }

    private function serializeHistory(OrderStatusHistory $history): array
    {
        return [
            'id' => $history->id,
            'from_status' => $history->from_status,
            'from_status_label' => $history->from_status ? $this->statusMeta($history->from_status)['label'] : null,
            'to_status' => $history->to_status,
            'to_status_label' => $this->statusMeta($history->to_status)['label'],
            'comment' => $history->comment,
            'user_name' => $history->user?->name,
            'created_at' => $this->adminDateTime($history->created_at),
        ];
    }

    private function statusOptions(): array
    {
        return collect(self::STATUS_OPTIONS)
            ->map(fn (array $meta, string $value): array => [
                'value' => $value,
                'label' => $meta['label'],
                'class' => $meta['class'],
            ])
            ->values()
            ->all();
    }

    private function statusMeta(string $status): array
    {
        $status = $this->normalizeOrderStatus($status);
        $meta = self::STATUS_OPTIONS[$status] ?? [
            'label' => $status,
            'class' => 'bg-slate-100 text-slate-600',
        ];

        return [
            'value' => $status,
            'label' => $meta['label'],
            'class' => $meta['class'],
        ];
    }

    private function normalizeOrderStatus(?string $status): string
    {
        return match ($status) {
            'awaiting_confirmation', 'pending_payment', null, '' => 'new',
            default => $status,
        };
    }

    private function statusFilterValues(string $status): array
    {
        return match ($status) {
            'new' => self::STATUS_GROUPS['new'],
            default => [$status],
        };
    }

    private function deliveryLine(Order $order): string
    {
        return collect([
            $order->delivery_branch,
            $order->delivery_address,
        ])
            ->filter(fn (?string $value): bool => trim((string) $value) !== '')
            ->first() ?? '';
    }

    private function hasFreeDelivery(Order $order): bool
    {
        if ((int) $order->delivery_price_cents > 0) {
            return false;
        }

        $snapshot = $order->delivery_snapshot ?? [];

        if (! is_array($snapshot)) {
            return false;
        }

        $freeFromCents = (int) ($snapshot['free_from_cents'] ?? 0);

        return $freeFromCents > 0 && (int) $order->subtotal_cents >= $freeFromCents;
    }

    private function sourceCode(Order $order): string
    {
        $source = mb_strtolower(trim((string) ($order->source ?: $order->utm_source)));

        return array_key_exists($source, self::SOURCE_LABELS) ? $source : 'other';
    }

    private function sourceClass(string $source): string
    {
        return match ($source) {
            'meta', 'facebook', 'instagram' => 'bg-slate-50 text-[#343241] ring-blue-100',
            'tiktok' => 'bg-slate-50 text-[#343241] ring-pink-100',
            'google' => 'bg-slate-50 text-[#343241] ring-red-100',
            default => 'bg-slate-50 text-[#343241] ring-slate-100',
        };
    }

    private function paymentTransactionStatusLabel(string $status): string
    {
        return [
            PaymentTransaction::STATUS_PENDING => 'Очікує',
            PaymentTransaction::STATUS_PAID => 'Оплачено',
            PaymentTransaction::STATUS_FAILED => 'Помилка',
            PaymentTransaction::STATUS_REFUNDED => 'Повернено',
            PaymentTransaction::STATUS_AMOUNT_MISMATCH => 'Сума не збігається',
        ][$status] ?? $status;
    }

    private function paymentTransactionStatusClass(string $status): string
    {
        return match ($status) {
            PaymentTransaction::STATUS_PAID => 'bg-emerald-50 text-emerald-700',
            PaymentTransaction::STATUS_FAILED, PaymentTransaction::STATUS_AMOUNT_MISMATCH => 'bg-red-50 text-red-700',
            PaymentTransaction::STATUS_REFUNDED => 'bg-amber-50 text-amber-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    private function paymentUi(Order $order): array
    {
        $status = $order->payment_status ?: 'unpaid';
        $method = $order->payment_method ?: '';
        $methodLabel = $this->paymentMethodLabel($method);
        $isCashOnDelivery = $this->isCashOnDeliveryMethod($method) || $status === 'cod';
        $amountDue = $this->amountDueCents($order);

        $tone = match ($status) {
            'paid' => 'paid',
            'failed' => 'failed',
            'refunded' => 'refunded',
            'pending' => 'pending',
            default => $isCashOnDelivery ? 'cod' : 'unpaid',
        };

        $statusLabel = match ($tone) {
            'paid' => 'Оплачено',
            'failed' => 'Помилка оплати',
            'refunded' => 'Повернено',
            'pending' => 'Очікує оплату',
            'cod' => 'При отриманні',
            default => 'Не оплачено',
        };

        $amountLabel = match ($tone) {
            'paid' => 'Сума: '.$this->formatMoney($order->total_cents, $order->currency),
            'refunded' => 'Сума: '.$this->formatMoney($order->total_cents, $order->currency),
            'failed' => 'Не оплачено: '.$this->formatMoney($order->total_cents, $order->currency),
            'pending' => 'Очікує: '.$this->formatMoney($amountDue, $order->currency),
            default => 'До оплати: '.$this->formatMoney($amountDue, $order->currency),
        };

        return [
            'tone' => $tone,
            'method_label' => $tone === 'cod' ? 'Післяплата' : $methodLabel,
            'status_label' => $statusLabel,
            'amount_label' => $amountLabel,
            'paid_at' => $this->adminDateTime($order->paid_at),
            'reference' => $order->payment_reference,
        ];
    }

    private function adminDateTime(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime
            ? CarbonImmutable::instance($dateTime)->setTimezone(self::ADMIN_TIMEZONE)->format('d.m.Y H:i')
            : null;
    }

    private function amountDueCents(Order $order): int
    {
        if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
            return 0;
        }

        return (int) $order->total_cents;
    }

    private function paymentMethodLabel(?string $method): string
    {
        $method = trim((string) $method);

        if ($method === '') {
            return 'Не вказано';
        }

        $normalized = str_replace('-', '_', mb_strtolower($method));

        if (isset(self::PAYMENT_METHOD_LABELS[$normalized])) {
            return self::PAYMENT_METHOD_LABELS[$normalized];
        }

        if ($this->isCashOnDeliveryMethod($normalized)) {
            return 'Оплата при отриманні';
        }

        if (str_contains($normalized, 'liqpay')) {
            return 'LiqPay';
        }

        if (str_contains($normalized, 'mono')) {
            return 'Monobank';
        }

        if (str_contains($normalized, 'card')) {
            return 'Оплата карткою';
        }

        return $method;
    }

    private function isCashOnDeliveryMethod(?string $method): bool
    {
        $method = str_replace('-', '_', mb_strtolower(trim((string) $method)));

        return in_array($method, ['cod', 'cash_on_delivery'], true)
            || str_contains($method, 'cash')
            || str_contains($method, 'cod')
            || str_contains($method, 'afterpay')
            || str_contains($method, 'after_payment');
    }

    private function itemImageUrl(OrderItem $item, array $snapshot): ?string
    {
        $snapshotImage = $snapshot['image_url'] ?? $snapshot['image'] ?? $snapshot['image_path'] ?? null;

        if (is_string($snapshotImage) && str_starts_with($snapshotImage, 'http')) {
            return $snapshotImage;
        }

        if (is_string($snapshotImage) && trim($snapshotImage) !== '') {
            return $this->imageUrl($snapshotImage);
        }

        $image = $item->variant?->images->firstWhere('is_main', true)
            ?? $item->variant?->images->first()
            ?? $item->product?->images->firstWhere('is_main', true)
            ?? $item->product?->images->first();

        return $image instanceof ProductImage ? $this->imageUrl($image->path) : null;
    }

    private function imageUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    private function formatMoney(?int $cents, ?string $currency = 'UAH'): string
    {
        $label = ($currency ?: 'UAH') === 'UAH' ? 'грн' : ($currency ?: 'UAH');

        return number_format(((int) $cents) / 100, 2, '.', ' ').' '.$label;
    }
}
