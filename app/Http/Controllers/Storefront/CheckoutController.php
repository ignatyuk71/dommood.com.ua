<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreCheckoutRequest;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Customer;
use App\Models\DeliveryMethod;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Marketing\MarketingEventService;
use App\Services\Marketing\StorefrontAnalyticsConfig;
use App\Services\Payments\LiqPayService;
use App\Services\SiteSettingsService;
use App\Services\Storefront\CartService;
use App\Services\Storefront\DeliveryPolicyService;
use App\Support\DateTime\KyivDateTime;
use App\Support\Marketing\MarketingSourceRouter;
use App\Support\Storefront\EcommerceAnalytics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly SiteSettingsService $settings,
        private readonly DeliveryPolicyService $deliveryPolicy,
        private readonly MarketingSourceRouter $sourceRouter,
        private readonly MarketingEventService $marketingEvents,
        private readonly StorefrontAnalyticsConfig $analyticsConfig,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->current($request);
        $cartPayload = $this->carts->payload($cart);

        if ($cartPayload['is_empty']) {
            return redirect()->route('cart.show');
        }

        $storeSettings = $this->settings->get('store');
        $checkoutAnalytics = EcommerceAnalytics::cart($cartPayload);
        $beginCheckoutEventId = $this->marketingEvents->trackBeginCheckout($request, $checkoutAnalytics);

        return view('storefront.checkout.index', [
            'storeName' => $storeSettings['store_name'] ?? 'DomMood',
            'supportEmail' => $storeSettings['support_email'] ?? null,
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'cart' => $cartPayload,
            'beginCheckoutEventId' => $beginCheckoutEventId,
            'deliveryMethods' => $this->deliveryMethods($cartPayload),
            'paymentMethods' => $this->paymentMethods(),
            'checkoutSettings' => $this->settings->get('checkout'),
            'canLogin' => Route::has('login'),
            'menuItems' => $this->menuItems('main', withFallback: true),
            'utilityLinks' => $this->menuItems('utility'),
            'mobileMenuItems' => $this->menuItems('mobile'),
            'footerMenuItems' => $this->menuItems('footer'),
            'storefrontAnalyticsConfig' => $this->analyticsConfig->storefront($request, $this->cartAttribution($request, $cart)),
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

        $deliveryType = (string) ($deliveryMethod['type'] ?? 'branch');
        $requiresWarehouse = in_array($deliveryType, ['branch', 'postomat'], true);
        $requiresCourierAddress = $deliveryType === 'courier';

        if ($requiresWarehouse && ! $request->filled('delivery_branch')) {
            $message = $deliveryType === 'postomat'
                ? 'Оберіть поштомат Нової пошти.'
                : 'Оберіть відділення Нової пошти.';

            throw ValidationException::withMessages(['delivery_branch' => $message]);
        }

        if ($requiresCourierAddress && ! $request->filled('delivery_address')) {
            throw ValidationException::withMessages(['delivery_address' => 'Вкажіть адресу для курʼєрської доставки.']);
        }

        $orderAttribution = $this->cartAttribution($request, $cart);

        $order = DB::transaction(function () use ($request, $cart, $cartPayload, $deliveryMethod, $paymentMethod, $deliveryType, $requiresWarehouse, $requiresCourierAddress, $orderAttribution): Order {
            $customer = $this->upsertCustomer($request);
            $deliveryPriceCents = (int) $deliveryMethod['price_cents'];
            $totalCents = (int) $cartPayload['total_cents'] + $deliveryPriceCents;
            $customerName = trim($request->string('customer_first_name').' '.$request->string('customer_last_name'));
            $checkoutSettings = $this->settings->get('checkout');
            $deliveryBranch = $requiresWarehouse ? $request->string('delivery_branch')->toString() : '';
            $deliveryBranchRef = $requiresWarehouse ? $request->string('delivery_branch_ref')->toString() : '';
            $deliveryAddress = $requiresCourierAddress ? $request->string('delivery_address')->toString() : '';
            $deliverySnapshot = array_merge($deliveryMethod, [
                'city_ref' => $request->string('delivery_city_ref')->toString() ?: null,
                'branch_ref' => $deliveryBranchRef ?: null,
                'city' => $request->string('delivery_city')->toString(),
                'branch' => $deliveryBranch ?: null,
                'address' => $deliveryAddress ?: null,
            ]);

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer?->id,
                'status' => $this->initialOrderStatus($checkoutSettings),
                'payment_status' => ($paymentMethod['type'] ?? null) === 'liqpay' ? 'pending' : 'unpaid',
                'payment_method' => $paymentMethod['code'],
                'payment_provider' => ($paymentMethod['type'] ?? null) === 'liqpay' ? 'liqpay' : null,
                'delivery_method' => $deliveryMethod['code'],
                'delivery_provider' => $deliveryMethod['provider'] ?? null,
                'delivery_type' => $deliveryType,
                'delivery_city' => $request->string('delivery_city')->toString(),
                'delivery_city_ref' => $request->string('delivery_city_ref')->toString() ?: null,
                'delivery_address' => $deliveryAddress ?: null,
                'delivery_branch' => $deliveryBranch ?: null,
                'delivery_branch_ref' => $deliveryBranchRef ?: null,
                'delivery_recipient_name' => $customerName,
                'delivery_recipient_phone' => $this->normalizePhone($request->string('customer_phone')->toString()),
                'delivery_snapshot' => $deliverySnapshot,
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
                'source' => $orderAttribution['source'] ?? null,
                'channel' => $orderAttribution['channel'] ?? null,
                'utm_source' => $orderAttribution['utm']['utm_source'] ?? null,
                'utm_medium' => $orderAttribution['utm']['utm_medium'] ?? null,
                'utm_campaign' => $orderAttribution['utm']['utm_campaign'] ?? null,
                'utm_content' => $orderAttribution['utm']['utm_content'] ?? null,
                'utm_term' => $orderAttribution['utm']['utm_term'] ?? null,
                'click_ids' => $orderAttribution['click_ids'] ?? [],
                'attribution' => $orderAttribution,
                'landing_page_url' => $orderAttribution['touch']['entry_url'] ?? $cart->landing_page_url ?? $request->fullUrl(),
                'referrer_url' => $orderAttribution['touch']['entry_referrer'] ?? $cart->referrer_url ?? $request->headers->get('referer'),
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

        $this->marketingEvents->trackPurchase($request, $order->load('items'));

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
            'supportEmail' => $storeSettings['support_email'] ?? null,
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'order' => $order,
            'liqPayPayload' => $liqPayPayload,
            'canLogin' => Route::has('login'),
            'menuItems' => $this->menuItems('main', withFallback: true),
            'utilityLinks' => $this->menuItems('utility'),
            'mobileMenuItems' => $this->menuItems('mobile'),
            'footerMenuItems' => $this->menuItems('footer'),
            'storefrontAnalyticsConfig' => $this->analyticsConfig->storefront($request, $this->orderAttribution($request, $order)),
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

        return $this->defaultNovaPoshtaDeliveryMethods();
    }

    private function cartAttribution(Request $request, Cart $cart): array
    {
        $captured = $this->sourceRouter->capture($request);
        $utm = array_filter([
            'utm_source' => $cart->utm_source,
            'utm_medium' => $cart->utm_medium,
            'utm_campaign' => $cart->utm_campaign,
            'utm_content' => $cart->utm_content,
            'utm_term' => $cart->utm_term,
        ], static fn ($value): bool => $value !== null && $value !== '');

        return [
            'source' => $cart->source ?: ($captured['source'] ?? null),
            'channel' => $cart->channel ?: ($captured['channel'] ?? null),
            'utm' => $utm ?: ($captured['utm'] ?? []),
            'click_ids' => $cart->click_ids ?: ($captured['click_ids'] ?? []),
            'touch' => array_filter([
                'entry_url' => $cart->landing_page_url ?: ($captured['touch']['entry_url'] ?? null),
                'entry_referrer' => $cart->referrer_url ?: ($captured['touch']['entry_referrer'] ?? null),
                'last_attributed_url' => $captured['touch']['last_attributed_url'] ?? null,
                'last_attributed_referrer' => $captured['touch']['last_attributed_referrer'] ?? null,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ];
    }

    private function orderAttribution(Request $request, Order $order): array
    {
        $captured = $this->sourceRouter->capture($request);
        $stored = is_array($order->attribution) ? $order->attribution : [];
        $utm = array_filter([
            'utm_source' => $order->utm_source,
            'utm_medium' => $order->utm_medium,
            'utm_campaign' => $order->utm_campaign,
            'utm_content' => $order->utm_content,
            'utm_term' => $order->utm_term,
        ], static fn ($value): bool => $value !== null && $value !== '');

        return [
            'source' => $order->source ?: ($stored['source'] ?? $captured['source'] ?? null),
            'channel' => $order->channel ?: ($stored['channel'] ?? $captured['channel'] ?? null),
            'utm' => $utm ?: ($stored['utm'] ?? $captured['utm'] ?? []),
            'click_ids' => $order->click_ids ?: ($stored['click_ids'] ?? $captured['click_ids'] ?? []),
            'touch' => $stored['touch'] ?? $captured['touch'] ?? [],
        ];
    }

    private function defaultNovaPoshtaDeliveryMethods(): array
    {
        $freeFromCents = $this->deliveryPolicy->freeShippingThresholdCents();

        return [
            [
                'name' => 'Нова пошта: відділення',
                'code' => 'nova_poshta_branch',
                'provider' => 'nova_poshta',
                'type' => 'branch',
                'description' => 'Отримання у відділенні Нової пошти. Вартість за тарифом перевізника.',
                'price_cents' => 0,
                'base_price_cents' => 0,
                'free_from_cents' => $freeFromCents,
                'is_free' => true,
            ],
            [
                'name' => 'Нова пошта: поштомат',
                'code' => 'nova_poshta_postomat',
                'provider' => 'nova_poshta',
                'type' => 'postomat',
                'description' => 'Отримання у поштоматі Нової пошти, якщо він доступний для обраного міста.',
                'price_cents' => 0,
                'base_price_cents' => 0,
                'free_from_cents' => $freeFromCents,
                'is_free' => true,
            ],
            [
                'name' => 'Курʼєр Нова пошта',
                'code' => 'nova_poshta_courier',
                'provider' => 'nova_poshta',
                'type' => 'courier',
                'description' => 'Доставка курʼєром за адресою. Вартість уточнюється за тарифом Нової пошти.',
                'price_cents' => 0,
                'base_price_cents' => 0,
                'free_from_cents' => $freeFromCents,
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

    private function initialOrderStatus(array $checkoutSettings): string
    {
        $status = (string) ($checkoutSettings['default_order_status'] ?? 'new');

        return match ($status) {
            'confirmed', 'processing' => $status,
            default => 'new',
        };
    }

    private function serializeDeliveryMethod(DeliveryMethod $method, int $cartTotalCents): array
    {
        $basePriceCents = (int) $method->base_price_cents;
        $freeFromCents = $this->deliveryPolicy->freeShippingThresholdCents();
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
        $userId = $request->user()?->id;

        if (! $userId && $phone === '' && $email === '') {
            return null;
        }

        $customer = $userId
            ? Customer::query()->where('user_id', $userId)->first()
            : null;

        if (! $customer && ($phone !== '' || $email !== '')) {
            $customer = Customer::query()
                ->where(function ($query) use ($phone, $email): void {
                    $query
                        ->when($phone !== '', fn ($query) => $query->orWhere('phone', $phone))
                        ->when($email !== '', fn ($query) => $query->orWhere('email', $email));
                })
                ->when($userId, fn ($query) => $query->where(function ($query) use ($userId): void {
                    $query->whereNull('user_id')->orWhere('user_id', $userId);
                }))
                ->first();
        }

        $customer ??= new Customer;

        $customer->fill([
            'user_id' => $userId ?: $customer->user_id,
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
            $number = 'DM-'.KyivDateTime::now()->format('ymd').'-'.random_int(1000, 9999);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '380')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '38')) {
            $digits = substr($digits, 2);
        }

        $digits = ltrim($digits, '0');

        return $digits !== '' ? '+380'.substr($digits, 0, 9) : '';
    }

    private function menuItems(string $slug, bool $withFallback = false): array
    {
        $menu = Menu::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'items' => fn ($query) => $query
                    ->active()
                    ->with('linkable')
                    ->orderByRaw('parent_id is not null')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->first();

        if (! $menu || $menu->items->isEmpty()) {
            return $withFallback ? $this->fallbackMenuItems() : [];
        }

        $itemsByParent = $menu->items->groupBy(fn (MenuItem $item): string => (string) ($item->parent_id ?: 'root'));

        $items = $itemsByParent
            ->get('root', collect())
            ->map(fn (MenuItem $item): array => $this->serializeMenuItem($item, $itemsByParent))
            ->values()
            ->all();

        return $items ?: ($withFallback ? $this->fallbackMenuItems() : []);
    }

    private function serializeMenuItem(MenuItem $item, $itemsByParent): array
    {
        return [
            'title' => $item->title,
            'url' => $this->menuItemUrl($item),
            'target' => $item->target ?: '_self',
            'badge' => $item->badge,
            'children' => $itemsByParent
                ->get((string) $item->id, collect())
                ->map(fn (MenuItem $child): array => $this->serializeMenuItem($child, $itemsByParent))
                ->values()
                ->all(),
        ];
    }

    private function fallbackMenuItems(): array
    {
        return [
            ['title' => 'Головна', 'url' => url('/'), 'target' => '_self', 'badge' => null, 'children' => []],
            ['title' => 'Каталог', 'url' => url('/catalog'), 'target' => '_self', 'badge' => null, 'children' => []],
            ['title' => 'Новинки', 'url' => url('/catalog?filter=new'), 'target' => '_self', 'badge' => 'New', 'children' => []],
        ];
    }

    private function menuItemUrl(MenuItem $item): string
    {
        $url = match ($item->type) {
            'category' => $item->linkable instanceof Category ? '/catalog/'.$item->linkable->slug : null,
            'page' => $item->linkable instanceof ContentPage ? '/'.$item->linkable->slug : null,
            default => $item->url,
        };

        $url = trim((string) $url);

        if ($url === '') {
            return '#';
        }

        if (Str::startsWith($url, ['http://', 'https://', '#'])) {
            return $url;
        }

        return url('/'.ltrim($url, '/'));
    }
}
