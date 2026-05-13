<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\DeliveryTariff;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Services\SiteSettingsService;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentDeliveryController extends Controller
{
    private const SECTIONS = ['delivery-methods', 'payment-methods', 'tariffs', 'transactions'];

    private const DELIVERY_PROVIDERS = [
        ['value' => 'manual', 'label' => 'Ручне налаштування'],
        ['value' => 'nova_poshta', 'label' => 'Нова пошта'],
        ['value' => 'ukrposhta', 'label' => 'Укрпошта'],
        ['value' => 'pickup', 'label' => 'Самовивіз'],
        ['value' => 'courier', 'label' => 'Курʼєр'],
    ];

    private const DELIVERY_TYPES = [
        ['value' => 'branch', 'label' => 'Відділення'],
        ['value' => 'postomat', 'label' => 'Поштомат'],
        ['value' => 'courier', 'label' => 'Курʼєр'],
        ['value' => 'pickup', 'label' => 'Самовивіз'],
    ];

    private const PAYMENT_TYPES = [
        ['value' => 'cod', 'label' => 'Оплата при отриманні'],
        ['value' => 'liqpay', 'label' => 'LiqPay'],
        ['value' => 'monobank', 'label' => 'Monobank'],
        ['value' => 'card', 'label' => 'Оплата карткою'],
    ];

    public function __construct(private readonly SiteSettingsService $settings) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.payment-delivery.show', $this->firstAllowedSection($request));
    }

    public function show(Request $request, string $section = 'delivery-methods'): Response
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        abort_unless($this->canViewSection($request, $section), 403);

        $deliveryMethods = $this->canViewSection($request, 'delivery-methods')
            ? DeliveryMethod::query()
                ->withCount('tariffs')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (DeliveryMethod $method): array => $this->serializeDeliveryMethod($method))
                ->values()
            : collect();

        $paymentMethods = $this->canViewSection($request, 'payment-methods')
            ? PaymentMethod::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (PaymentMethod $method): array => $this->serializePaymentMethod($method))
                ->values()
            : collect();

        $tariffs = $this->canViewSection($request, 'tariffs')
            ? DeliveryTariff::query()
                ->with('deliveryMethod')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (DeliveryTariff $tariff): array => $this->serializeTariff($tariff))
                ->values()
            : collect();

        $transactions = $this->canViewSection($request, 'transactions')
            ? PaymentTransaction::query()
                ->with('order:id,order_number,customer_name,total_cents,currency')
                ->latestFirst()
                ->limit(100)
                ->get()
                ->map(fn (PaymentTransaction $transaction): array => $this->serializeTransaction($transaction))
                ->values()
            : collect();

        return Inertia::render('Admin/PaymentDelivery/Index', [
            'section' => $section,
            'tabs' => $this->tabs($request),
            'deliveryMethods' => $deliveryMethods,
            'paymentMethods' => $paymentMethods,
            'tariffs' => $tariffs,
            'transactions' => $transactions,
            'options' => [
                'deliveryProviders' => self::DELIVERY_PROVIDERS,
                'deliveryTypes' => self::DELIVERY_TYPES,
                'paymentTypes' => self::PAYMENT_TYPES,
                'paymentConnections' => $this->paymentConnections(),
            ],
            'stats' => [
                'active_delivery_methods' => $deliveryMethods->where('is_active', true)->count(),
                'active_payment_methods' => $paymentMethods->where('is_active', true)->count(),
                'active_tariffs' => $tariffs->where('is_active', true)->count(),
                'paid_transactions' => $this->canViewSection($request, 'transactions')
                    ? PaymentTransaction::query()
                        ->where('status', PaymentTransaction::STATUS_PAID)
                        ->count()
                    : 0,
            ],
        ]);
    }

    public function storeDeliveryMethod(Request $request): RedirectResponse
    {
        $data = $this->validateDeliveryMethod($request);

        DeliveryMethod::query()->create($this->deliveryPayload($data));

        return redirect()
            ->route('admin.payment-delivery.show', 'delivery-methods')
            ->with('success', 'Метод доставки створено');
    }

    public function updateDeliveryMethod(Request $request, DeliveryMethod $deliveryMethod): RedirectResponse
    {
        $data = $this->validateDeliveryMethod($request, $deliveryMethod->id);

        $deliveryMethod->update($this->deliveryPayload($data, $deliveryMethod->id));

        return redirect()
            ->route('admin.payment-delivery.show', 'delivery-methods')
            ->with('success', 'Метод доставки оновлено');
    }

    public function destroyDeliveryMethod(DeliveryMethod $deliveryMethod): RedirectResponse
    {
        $deliveryMethod->delete();

        return redirect()
            ->route('admin.payment-delivery.show', 'delivery-methods')
            ->with('success', 'Метод доставки видалено');
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $data = $this->validatePaymentMethod($request);

        PaymentMethod::query()->create($this->paymentPayload($data));

        return redirect()
            ->route('admin.payment-delivery.show', 'payment-methods')
            ->with('success', 'Метод оплати створено');
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $this->validatePaymentMethod($request, $paymentMethod->id);

        $paymentMethod->update($this->paymentPayload($data, $paymentMethod->id));

        return redirect()
            ->route('admin.payment-delivery.show', 'payment-methods')
            ->with('success', 'Метод оплати оновлено');
    }

    public function destroyPaymentMethod(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        return redirect()
            ->route('admin.payment-delivery.show', 'payment-methods')
            ->with('success', 'Метод оплати видалено');
    }

    public function storeTariff(Request $request): RedirectResponse
    {
        $data = $this->validateTariff($request);

        DeliveryTariff::query()->create($this->tariffPayload($data));

        return redirect()
            ->route('admin.payment-delivery.show', 'tariffs')
            ->with('success', 'Тариф створено');
    }

    public function updateTariff(Request $request, DeliveryTariff $tariff): RedirectResponse
    {
        $data = $this->validateTariff($request, $tariff->id);

        $tariff->update($this->tariffPayload($data, $tariff->id));

        return redirect()
            ->route('admin.payment-delivery.show', 'tariffs')
            ->with('success', 'Тариф оновлено');
    }

    public function destroyTariff(DeliveryTariff $tariff): RedirectResponse
    {
        $tariff->delete();

        return redirect()
            ->route('admin.payment-delivery.show', 'tariffs')
            ->with('success', 'Тариф видалено');
    }

    private function tabs(Request $request): array
    {
        return collect([
            ['value' => 'delivery-methods', 'label' => 'Методи доставки', 'route' => route('admin.payment-delivery.show', 'delivery-methods')],
            ['value' => 'payment-methods', 'label' => 'Методи оплати', 'route' => route('admin.payment-delivery.show', 'payment-methods')],
            ['value' => 'tariffs', 'label' => 'Тарифи', 'route' => route('admin.payment-delivery.show', 'tariffs')],
            ['value' => 'transactions', 'label' => 'Транзакції', 'route' => route('admin.payment-delivery.show', 'transactions')],
        ])
            ->filter(fn (array $tab): bool => $this->canViewSection($request, $tab['value']))
            ->values()
            ->all();
    }

    private function firstAllowedSection(Request $request): string
    {
        foreach (self::SECTIONS as $section) {
            if ($this->canViewSection($request, $section)) {
                return $section;
            }
        }

        abort(403);
    }

    private function canViewSection(Request $request, string $section): bool
    {
        return collect($this->sectionPermissions($section))
            ->contains(fn (string $permission): bool => (bool) $request->user()?->can($permission));
    }

    private function sectionPermissions(string $section): array
    {
        return match ($section) {
            'delivery-methods' => [AdminPermissions::DELIVERY_METHODS_VIEW, AdminPermissions::DELIVERY_METHODS_MANAGE],
            'payment-methods' => [AdminPermissions::PAYMENT_METHODS_VIEW, AdminPermissions::PAYMENT_METHODS_MANAGE],
            'tariffs' => [AdminPermissions::DELIVERY_TARIFFS_VIEW, AdminPermissions::DELIVERY_TARIFFS_MANAGE],
            'transactions' => [AdminPermissions::PAYMENT_TRANSACTIONS_VIEW],
            default => [],
        };
    }

    private function validateDeliveryMethod(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:120', Rule::unique('delivery_methods', 'code')->ignore($ignoreId)],
            'provider' => ['required', Rule::in(collect(self::DELIVERY_PROVIDERS)->pluck('value')->all())],
            'type' => ['required', Rule::in(collect(self::DELIVERY_TYPES)->pluck('value')->all())],
            'description' => ['nullable', 'string', 'max:1000'],
            'base_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'free_from' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function validatePaymentMethod(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:120', Rule::unique('payment_methods', 'code')->ignore($ignoreId)],
            'type' => ['required', Rule::in(collect(self::PAYMENT_TYPES)->pluck('value')->all())],
            'description' => ['nullable', 'string', 'max:1000'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_fee' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function validateTariff(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'delivery_method_id' => ['nullable', 'integer', 'exists:delivery_methods,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:120', Rule::unique('delivery_tariffs', 'code')->ignore($ignoreId)],
            'region' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'max_order' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'free_from' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function deliveryPayload(array $data, ?int $ignoreId = null): array
    {
        return [
            'name' => $data['name'],
            'code' => $this->uniqueCode('delivery_methods', $data['code'] ?? null, $data['name'], $ignoreId),
            'provider' => $data['provider'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'base_price_cents' => $this->moneyToCents($data['base_price'] ?? 0),
            'free_from_cents' => $this->nullableMoneyToCents($data['free_from'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function paymentPayload(array $data, ?int $ignoreId = null): array
    {
        return [
            'name' => $data['name'],
            'code' => $this->uniqueCode('payment_methods', $data['code'] ?? null, $data['name'], $ignoreId),
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'fee_percent' => (float) ($data['fee_percent'] ?? 0),
            'fixed_fee_cents' => $this->moneyToCents($data['fixed_fee'] ?? 0),
            'settings' => [],
            'secret_settings' => [],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function tariffPayload(array $data, ?int $ignoreId = null): array
    {
        return [
            'delivery_method_id' => $data['delivery_method_id'] ?? null,
            'name' => $data['name'],
            'code' => $this->uniqueCode('delivery_tariffs', $data['code'] ?? null, $data['name'], $ignoreId),
            'region' => $data['region'] ?? null,
            'city' => $data['city'] ?? null,
            'min_order_cents' => $this->moneyToCents($data['min_order'] ?? 0),
            'max_order_cents' => $this->nullableMoneyToCents($data['max_order'] ?? null),
            'price_cents' => $this->moneyToCents($data['price'] ?? 0),
            'free_from_cents' => $this->nullableMoneyToCents($data['free_from'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function serializeDeliveryMethod(DeliveryMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'code' => $method->code,
            'provider' => $method->provider,
            'provider_label' => $this->label(self::DELIVERY_PROVIDERS, $method->provider),
            'type' => $method->type,
            'type_label' => $this->label(self::DELIVERY_TYPES, $method->type),
            'description' => $method->description,
            'base_price' => $this->formatMoney($method->base_price_cents),
            'base_price_value' => $this->moneyValue($method->base_price_cents),
            'free_from' => $this->formatNullableMoney($method->free_from_cents),
            'free_from_value' => $this->moneyValue($method->free_from_cents),
            'is_active' => $method->is_active,
            'sort_order' => $method->sort_order,
            'tariffs_count' => $method->tariffs_count ?? 0,
        ];
    }

    private function serializePaymentMethod(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'code' => $method->code,
            'type' => $method->type,
            'type_label' => $this->label(self::PAYMENT_TYPES, $method->type),
            'description' => $method->description,
            'fee_percent' => (string) $method->fee_percent,
            'fixed_fee' => $this->formatMoney($method->fixed_fee_cents),
            'fixed_fee_value' => $this->moneyValue($method->fixed_fee_cents),
            'connection' => $this->paymentConnectionFor($method->type),
            'is_active' => $method->is_active,
            'sort_order' => $method->sort_order,
        ];
    }

    private function paymentConnections(): array
    {
        $payments = $this->settings->get('payments');

        return [
            'liqpay' => [
                'label' => 'LiqPay',
                'configured' => (bool) ($payments['liqpay_enabled'] ?? false)
                    && filled($payments['liqpay_public_key'] ?? null)
                    && filled($payments['liqpay_private_key'] ?? null),
                'enabled' => (bool) ($payments['liqpay_enabled'] ?? false),
                'mode' => $payments['liqpay_mode'] ?? 'test',
                'settings_route' => route('admin.settings.site.show', 'payments'),
            ],
            'monobank' => [
                'label' => 'Monobank',
                'configured' => (bool) ($payments['monobank_enabled'] ?? false)
                    && filled($payments['monobank_token'] ?? null),
                'enabled' => (bool) ($payments['monobank_enabled'] ?? false),
                'mode' => $payments['monobank_mode'] ?? 'test',
                'settings_route' => route('admin.settings.site.show', 'payments'),
            ],
        ];
    }

    private function paymentConnectionFor(string $type): ?array
    {
        return $this->paymentConnections()[$type] ?? null;
    }

    private function serializeTransaction(PaymentTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'order_number' => $transaction->order?->order_number ?: $transaction->external_order_id,
            'customer_name' => $transaction->order?->customer_name,
            'provider' => $transaction->provider,
            'provider_label' => $this->paymentProviderLabel($transaction->provider),
            'external_order_id' => $transaction->external_order_id,
            'provider_transaction_id' => $transaction->provider_transaction_id,
            'payment_method' => $transaction->payment_method,
            'action' => $transaction->action,
            'status' => $transaction->status,
            'status_label' => $this->transactionStatusLabel($transaction->status),
            'status_class' => $this->transactionStatusClass($transaction->status),
            'amount' => $this->formatMoney($transaction->amount_cents, $transaction->currency),
            'amount_cents' => $transaction->amount_cents,
            'currency' => $transaction->currency,
            'is_test' => $transaction->is_test,
            'failure_reason' => $transaction->failure_reason,
            'processed_at' => $transaction->processed_at?->format('d.m.Y H:i'),
            'paid_at' => $transaction->paid_at?->format('d.m.Y H:i'),
            'created_at' => $transaction->created_at?->format('d.m.Y H:i'),
        ];
    }

    private function serializeTariff(DeliveryTariff $tariff): array
    {
        return [
            'id' => $tariff->id,
            'delivery_method_id' => $tariff->delivery_method_id,
            'delivery_method_name' => $tariff->deliveryMethod?->name,
            'name' => $tariff->name,
            'code' => $tariff->code,
            'region' => $tariff->region,
            'city' => $tariff->city,
            'min_order' => $this->formatMoney($tariff->min_order_cents),
            'min_order_value' => $this->moneyValue($tariff->min_order_cents),
            'max_order' => $this->formatNullableMoney($tariff->max_order_cents),
            'max_order_value' => $this->moneyValue($tariff->max_order_cents),
            'price' => $this->formatMoney($tariff->price_cents),
            'price_value' => $this->moneyValue($tariff->price_cents),
            'free_from' => $this->formatNullableMoney($tariff->free_from_cents),
            'free_from_value' => $this->moneyValue($tariff->free_from_cents),
            'is_active' => $tariff->is_active,
            'sort_order' => $tariff->sort_order,
        ];
    }

    private function label(array $options, string $value): string
    {
        return collect($options)->firstWhere('value', $value)['label'] ?? $value;
    }

    private function transactionStatusLabel(string $status): string
    {
        return [
            PaymentTransaction::STATUS_PENDING => 'Очікує',
            PaymentTransaction::STATUS_PAID => 'Оплачено',
            PaymentTransaction::STATUS_FAILED => 'Помилка',
            PaymentTransaction::STATUS_REFUNDED => 'Повернено',
            PaymentTransaction::STATUS_AMOUNT_MISMATCH => 'Сума не збігається',
        ][$status] ?? $status;
    }

    private function transactionStatusClass(string $status): string
    {
        return match ($status) {
            PaymentTransaction::STATUS_PAID => 'bg-emerald-50 text-emerald-700',
            PaymentTransaction::STATUS_FAILED, PaymentTransaction::STATUS_AMOUNT_MISMATCH => 'bg-red-50 text-red-700',
            PaymentTransaction::STATUS_REFUNDED => 'bg-amber-50 text-amber-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    private function uniqueCode(string $table, ?string $code, string $name, ?int $ignoreId = null): string
    {
        $base = trim((string) $code) ?: Str::slug($name, '_');
        $base = $base !== '' ? $base : Str::random(8);
        $candidate = $base;
        $suffix = 2;

        while ($this->codeExists($table, $candidate, $ignoreId)) {
            $candidate = "{$base}_{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function codeExists(string $table, string $code, ?int $ignoreId): bool
    {
        return DB::table($table)
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) str_replace(',', '.', (string) $value)) * 100);
    }

    private function nullableMoneyToCents(mixed $value): ?int
    {
        return trim((string) $value) === '' ? null : $this->moneyToCents($value);
    }

    private function moneyValue(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }

    private function formatNullableMoney(?int $cents): string
    {
        return $cents === null ? '—' : $this->formatMoney($cents);
    }

    private function formatMoney(?int $cents, string $currency = 'UAH'): string
    {
        $label = $currency === 'UAH' ? 'грн' : $currency;

        return number_format(((int) $cents) / 100, 2, '.', ' ').' '.$label;
    }

    private function paymentProviderLabel(string $provider): string
    {
        return [
            PaymentTransaction::PROVIDER_LIQPAY => 'LiqPay',
            PaymentTransaction::PROVIDER_MONOBANK => 'Monobank',
        ][$provider] ?? $provider;
    }
}
