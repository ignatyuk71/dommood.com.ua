<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreCheckoutRequest;
use App\Models\Customer;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payments\LiqPayService;
use App\Services\SiteSettingsService;
use App\Services\Storefront\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly SiteSettingsService $settings,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current($request);
        $cartPayload = $this->carts->payload($cart);

        if ($cartPayload['is_empty']) {
            return redirect()->route('cart.show');
        }

        $storeSettings = $this->settings->get('store');

        return view('storefront.checkout.index', [
            'storeName' => $storeSettings['store_name'] ?? 'DomMood',
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'cart' => $cartPayload,
            'deliveryMethods' => $this->deliveryMethods($cartPayload),
            'paymentMethods' => $this->paymentMethods(),
            'checkoutSettings' => $this->settings->get('checkout'),
        ]);
    }

    public function store(StoreCheckoutRequest $request): RedirectResponse
    {
        $cart = $this->carts->findCurrent($request);

        if (! $cart) {
            return redirect()->route('cart.show');
        }

        $cartPayload = $this->carts->payload($cart);

        if ($cartPayload['is_empty']) {
            return redirect()->route('cart.show');
        }

        $deliveryMethods = collect($this->deliveryMethods($cartPayload));
        $paymentMethods = collect($this->paymentMethods());
        $deliveryMethod = $deliveryMethods->firstWhere('code', $request->string('delivery_method')->toString());
        $paymentMethod = $paymentMethods->firstWhere('code', $request->string('payment_method')->toString());

        if (! $deliveryMethod) {
            throw ValidationException::withMessages(['delivery_method' => 'Оберіть доступний спосіб доставки.']);
        }

        if (! $paymentMethod) {
            throw ValidationException::withMessages(['payment_method' => 'Оберіть доступний спосіб оплати.']);
        }

        $order = DB::transaction(function () use ($request, $cart, $cartPayload, $deliveryMethod, $paymentMethod): Order {
            $customer = $this->upsertCustomer($request);
            $deliveryPriceCents = (int) $deliveryMethod['price_cents'];
            $totalCents = (int) $cartPayload['total_cents'] + $deliveryPriceCents;
            $customerName = trim($request->string('customer_first_name').' '.$request->string('customer_last_name'));
            $checkoutSettings = $this->settings->get('checkout');

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer?->id,
                'status' => $checkoutSettings['default_order_status'] ?? 'awaiting_confirmation',
                'payment_status' => ($paymentMethod['type'] ?? null) === 'liqpay' ? 'pending' : 'unpaid',
                'payment_method' => $paymentMethod['code'],
                'payment_provider' => ($paymentMethod['type'] ?? null) === 'liqpay' ? 'liqpay' : null,
                'delivery_method' => $deliveryMethod['code'],
                'delivery_provider' => $deliveryMethod['provider'] ?? null,
                'delivery_type' => $deliveryMethod['type'] ?? null,
                'delivery_city' => $request->string('delivery_city')->toString(),
                'delivery_address' => $request->string('delivery_address')->toString() ?: null,
                'delivery_branch' => $request->string('delivery_branch')->toString() ?: null,
                'delivery_recipient_name' => $customerName,
                'delivery_recipient_phone' => $this->normalizePhone($request->string('customer_phone')->toString()),
                'delivery_snapshot' => $deliveryMethod,
                'customer_name' => $customerName,
                'customer_phone' => $this->normalizePhone($request->string('customer_phone')->toString()),
                'customer_email' => $request->string('customer_email')->toString() ?: null,
                'currency' => $cartPayload['currency'],
                'subtotal_cents' => (int) $cartPayload['subtotal_cents'],
                'discount_total_cents' => (int) $cartPayload['discount_total_cents'],
                'delivery_price_cents' => $deliveryPriceCents,
                'total_cents' => $totalCents,
                'promocode_code' => $cartPayload['promocode_code'],
                'comment' => $request->string('comment')->toString() ?: null,
                'source' => $cart->utm_source ?: $request->headers->get('referer'),
                'utm_source' => $cart->utm_source,
                'utm_medium' => $cart->utm_medium,
                'utm_campaign' => $cart->utm_campaign,
                'utm_content' => $cart->utm_content,
                'utm_term' => $cart->utm_term,
                'landing_page_url' => $request->headers->get('referer'),
                'referrer_url' => $request->headers->get('referer'),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ]);

            foreach ($cartPayload['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $item['name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'price_cents' => $item['price_cents'],
                    'quantity' => $item['quantity'],
                    'total_cents' => $item['total_cents'],
                    'product_snapshot' => $item,
                ]);
            }

            $this->carts->markConverted($request, $cart);
            $this->syncCustomerStats($customer, $order);

            return $order;
        });

        $request->session()->put('last_order_number', $order->order_number);

        return redirect()->route('checkout.thank-you', $order->order_number);
    }

    public function thankYou(Request $request, string $orderNumber, LiqPayService $liqPay): View
    {
        $order = Order::query()
            ->with('items')
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $liqPayPayload = null;

        if ($order->payment_provider === 'liqpay') {
            $method = PaymentMethod::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->where('code', $order->payment_method)
                    ->orWhere('type', 'liqpay'))
                ->orderBy('sort_order')
                ->first();

            try {
                $liqPayPayload = $liqPay->checkoutPayload($order, $method);
            } catch (ValidationException) {
                $liqPayPayload = null;
            }
        }

        $storeSettings = $this->settings->get('store');

        return view('storefront.checkout.thank-you', [
            'storeName' => $storeSettings['store_name'] ?? 'DomMood',
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'order' => $order,
            'liqPayPayload' => $liqPayPayload,
        ]);
    }

    private function deliveryMethods(array $cart): array
    {
        $methods = DeliveryMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DeliveryMethod $method): array => $this->serializeDeliveryMethod($method, (int) $cart['total_cents']))
            ->values()
            ->all();

        if ($methods !== []) {
            return $methods;
        }

        return [
            [
                'name' => 'Нова пошта',
                'code' => 'nova_poshta_branch',
                'provider' => 'nova_poshta',
                'type' => 'branch',
                'description' => 'Відділення або поштомат. Менеджер підтвердить точну вартість після замовлення.',
                'price_cents' => 0,
                'base_price_cents' => 0,
                'free_from_cents' => null,
                'is_free' => true,
            ],
        ];
    }

    private function paymentMethods(): array
    {
        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentMethod $method): array => [
                'name' => $method->name,
                'code' => $method->code,
                'type' => $method->type,
                'description' => $method->description,
            ])
            ->values()
            ->all();

        if ($methods !== []) {
            return $methods;
        }

        return [
            [
                'name' => 'Оплата при отриманні',
                'code' => 'cod',
                'type' => 'manual',
                'description' => 'Оплачуйте після перевірки товару у відділенні або курʼєру.',
            ],
        ];
    }

    private function serializeDeliveryMethod(DeliveryMethod $method, int $cartTotalCents): array
    {
        $basePriceCents = (int) $method->base_price_cents;
        $freeFromCents = $method->free_from_cents ? (int) $method->free_from_cents : null;
        $priceCents = $freeFromCents && $cartTotalCents >= $freeFromCents ? 0 : $basePriceCents;

        return [
            'name' => $method->name,
            'code' => $method->code,
            'provider' => $method->provider,
            'type' => $method->type,
            'description' => $method->description,
            'price_cents' => $priceCents,
            'base_price_cents' => $basePriceCents,
            'free_from_cents' => $freeFromCents,
            'is_free' => $priceCents === 0,
        ];
    }

    private function upsertCustomer(StoreCheckoutRequest $request): ?Customer
    {
        $phone = $this->normalizePhone($request->string('customer_phone')->toString());
        $email = $request->string('customer_email')->toString();

        if ($phone === '' && $email === '') {
            return null;
        }

        $customer = Customer::query()
            ->when($phone !== '', fn ($query) => $query->orWhere('phone', $phone))
            ->when($email !== '', fn ($query) => $query->orWhere('email', $email))
            ->first() ?? new Customer();

        $customer->fill([
            'user_id' => $request->user()?->id,
            'first_name' => $request->string('customer_first_name')->toString(),
            'last_name' => $request->string('customer_last_name')->toString() ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'source' => $customer->source ?: $request->headers->get('referer'),
        ]);

        if (! $customer->exists) {
            $customer->first_order_at = now();
        }

        $customer->save();

        return $customer;
    }

    private function syncCustomerStats(?Customer $customer, Order $order): void
    {
        if (! $customer) {
            return;
        }

        $customer->forceFill([
            'orders_count' => (int) $customer->orders_count + 1,
            'total_spent_cents' => (int) $customer->total_spent_cents + (int) $order->total_cents,
            'last_order_at' => now(),
        ])->save();
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'DM-'.now()->format('ymd').'-'.random_int(1000, 9999);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone) ?: '';
    }
}
