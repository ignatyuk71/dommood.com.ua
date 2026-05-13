<?php

use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\FeedExportController;
use App\Http\Controllers\Payment\LiqPayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShippingLookupController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::redirect('/bezkoshtovne-povernennia', '/bezkoshtovne-povernennia-novoiu-poshtoiu', 301);
Route::redirect('/bezkoshtovne-povernennia/', '/bezkoshtovne-povernennia-novoiu-poshtoiu', 301);
Route::redirect('/obmin-ta-povernennia', '/obmin-ta-povernennya', 301);
Route::redirect('/obmin-ta-povernennia/', '/obmin-ta-povernennya', 301);
Route::redirect('/uhoda-korystuvacha-oferta', '/uhoda-korystuvacha', 301);
Route::redirect('/uhoda-korystuvacha-oferta/', '/uhoda-korystuvacha', 301);
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalog/{categorySlug}/filter/{filterSegments?}', [CatalogController::class, 'index'])
    ->where('filterSegments', '.*')
    ->name('catalog.category.filter');
Route::get('/catalog/{categorySlug}', [CatalogController::class, 'index'])->name('catalog.category');
Route::get('/catalog/{categorySlug}/{productSlug}', [CatalogController::class, 'show'])->name('catalog.product');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::get('/cart/drawer', [CartController::class, 'drawer'])->name('cart.drawer');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::post('/cart/promocode', [CartController::class, 'applyPromocode'])->name('cart.promocode.apply');
Route::delete('/cart/promocode', [CartController::class, 'clearPromocode'])->name('cart.promocode.clear');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/thank-you/{orderNumber}', [CheckoutController::class, 'thankYou'])->name('checkout.thank-you');

Route::get('/feeds/google-merchant.xml', [FeedExportController::class, 'googleMerchant'])
    ->name('feeds.google-merchant');
Route::get('/feeds/meta-catalog.csv', [FeedExportController::class, 'metaCatalog'])
    ->name('feeds.meta-catalog');
Route::get('/feeds/tiktok-catalog.csv', [FeedExportController::class, 'tiktokCatalog'])
    ->name('feeds.tiktok-catalog');

Route::post('/payments/liqpay/callback', [LiqPayController::class, 'callback'])
    ->name('payments.liqpay.callback');
Route::match(['get', 'post'], '/payments/liqpay/result', [LiqPayController::class, 'result'])
    ->name('payments.liqpay.result');

Route::prefix('shipping/nova-poshta')
    ->name('shipping.nova-poshta.')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('cities', [ShippingLookupController::class, 'novaPoshtaCities'])->name('cities');
        Route::get('warehouses', [ShippingLookupController::class, 'novaPoshtaWarehouses'])->name('warehouses');
        Route::post('price', [ShippingLookupController::class, 'novaPoshtaPrice'])->name('price');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('account')
    ->name('account.')
    ->group(function (): void {
        Route::get('/', AccountDashboardController::class)->name('dashboard');
    });

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin$|dashboard$|login$|register$|forgot-password$|reset-password$|verify-email$|confirm-password$|catalog$|account$|profile$|cart$|checkout$|feeds$|payments$|shipping$)[A-Za-z0-9-]+$')
    ->name('pages.show');

Route::get('/{slug}/', [PageController::class, 'show'])
    ->where('slug', '^(?!admin$|dashboard$|login$|register$|forgot-password$|reset-password$|verify-email$|confirm-password$|catalog$|account$|profile$|cart$|checkout$|feeds$|payments$|shipping$)[A-Za-z0-9-]+$');
