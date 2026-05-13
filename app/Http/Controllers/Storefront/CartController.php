<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingsService;
use App\Services\Storefront\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly SiteSettingsService $settings,
    ) {}

    public function show(Request $request): View
    {
        $cart = $this->carts->current($request);
        $storeSettings = $this->settings->get('store');

        return view('storefront.cart.show', [
            'storeName' => $storeSettings['store_name'] ?? 'DomMood',
            'supportPhone' => $storeSettings['support_phone'] ?? null,
            'cart' => $this->carts->payload($cart),
            'recommendedProducts' => $this->carts->recommendedProducts($cart),
            'checkoutSettings' => $this->settings->get('checkout'),
        ]);
    }

    public function drawer(Request $request): JsonResponse
    {
        return $this->drawerResponse($request);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $this->carts->addProduct(
            $request,
            (int) $data['product_id'],
            isset($data['product_variant_id']) ? (int) $data['product_variant_id'] : null,
            (int) ($data['quantity'] ?? 1),
        );

        if ($this->wantsDrawerResponse($request)) {
            return $this->drawerResponse($request, 'Товар додано до кошика.');
        }

        return redirect()
            ->route('cart.show')
            ->with('cart_status', 'Товар додано до кошика.');
    }

    public function update(Request $request, int $item): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:0', 'max:99'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        $cart = $this->carts->current($request);
        $this->carts->updateItem(
            $cart,
            $item,
            array_key_exists('quantity', $data) ? (int) $data['quantity'] : null,
            array_key_exists('product_variant_id', $data) ? (int) $data['product_variant_id'] : null,
            array_key_exists('product_variant_id', $data),
        );

        if ($this->wantsDrawerResponse($request)) {
            return $this->drawerResponse($request, 'Кошик оновлено.');
        }

        return redirect()
            ->route('cart.show')
            ->with('cart_status', 'Кошик оновлено.');
    }

    public function destroy(Request $request, int $item): RedirectResponse|JsonResponse
    {
        $cart = $this->carts->current($request);
        $this->carts->removeItem($cart, $item);

        if ($this->wantsDrawerResponse($request)) {
            return $this->drawerResponse($request, 'Товар видалено з кошика.');
        }

        return redirect()
            ->route('cart.show')
            ->with('cart_status', 'Товар видалено з кошика.');
    }

    public function applyPromocode(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $cart = $this->carts->current($request);
        $this->carts->applyPromocode($cart, (string) $data['code']);

        if ($this->wantsDrawerResponse($request)) {
            return $this->drawerResponse($request, 'Купон застосовано.');
        }

        return redirect()
            ->route('cart.show')
            ->with('cart_status', 'Купон застосовано.');
    }

    public function clearPromocode(Request $request): RedirectResponse|JsonResponse
    {
        $cart = $this->carts->current($request);
        $this->carts->clearPromocode($cart);

        if ($this->wantsDrawerResponse($request)) {
            return $this->drawerResponse($request, 'Купон видалено.');
        }

        return redirect()
            ->route('cart.show')
            ->with('cart_status', 'Купон видалено.');
    }

    private function drawerResponse(Request $request, ?string $statusMessage = null): JsonResponse
    {
        $cart = $this->carts->current($request);
        $payload = $this->carts->payload($cart);

        return response()->json([
            'drawer_html' => view('storefront.partials.cart-drawer', [
                'cart' => $payload,
                'recommendedProducts' => $this->carts->recommendedProducts($cart),
                'drawerOpen' => true,
                'cartPage' => false,
            ])->render(),
            'items_count' => $payload['items_count'],
            'quantity_count' => $payload['quantity_count'],
            'is_empty' => $payload['is_empty'],
            'cart_summary' => $this->carts->summaryFromPayload($payload),
            'status_message' => $statusMessage,
        ]);
    }

    private function wantsDrawerResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax();
    }
}
