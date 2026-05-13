<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManagerActivityController;
use App\Http\Controllers\Admin\NovaPoshtaSettingsController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentDeliveryController;
use App\Http\Controllers\Admin\ProductAttributeController;
use App\Http\Controllers\Admin\ProductColorGroupController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductFeedController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\SiteStructureController;
use App\Http\Controllers\Admin\SizeChartController;
use App\Http\Controllers\Admin\SystemCacheController;
use App\Http\Controllers\Admin\TrackingSettingsController;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Route;

Route::redirect('/dashboard', '/admin');
Route::get('/admin', DashboardController::class)
    ->middleware(['auth', 'verified', 'admin.access', 'admin.permission:admin.dashboard.view', 'admin.activity.visit'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->middleware('admin.activity.visit')
    ->group(function (): void {
        Route::middleware('admin.permission:'.AdminPermissions::PRODUCTS_MANAGE)->group(function (): void {
            Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])
                ->name('products.duplicate');
            Route::patch('products/{product}/quick', [ProductController::class, 'quickUpdate'])
                ->name('products.quick');
            Route::patch('products/{product}/variants/{variant}', [ProductController::class, 'updateVariant'])
                ->name('products.variants.update');
            Route::delete('products/{product}/variants/{variant}', [ProductController::class, 'destroyVariant'])
                ->name('products.variants.destroy');
            Route::resource('products', ProductController::class)
                ->except(['index', 'show']);
        });

        Route::resource('products', ProductController::class)
            ->only(['index'])
            ->middleware('admin.permission:'.AdminPermissions::PRODUCTS_VIEW.'|'.AdminPermissions::PRODUCTS_MANAGE);

        Route::middleware('admin.permission:'.AdminPermissions::PRODUCT_FEEDS_VIEW.'|'.AdminPermissions::PRODUCT_FEEDS_MANAGE)->group(function (): void {
            Route::get('product-feeds', [ProductFeedController::class, 'index'])
                ->name('product-feeds.index');
            Route::get('products/{product}/feeds', [ProductFeedController::class, 'edit'])
                ->name('product-feeds.edit');
        });

        Route::middleware('admin.permission:'.AdminPermissions::PRODUCT_FEEDS_MANAGE)->group(function (): void {
            Route::match(['put', 'patch'], 'products/{product}/feeds', [ProductFeedController::class, 'update'])
                ->name('product-feeds.update');
        });

        Route::middleware('admin.permission:admin.analytics.view')->group(function (): void {
            Route::get('analytics', [AnalyticsController::class, 'index'])
                ->name('analytics.index');
            Route::get('analytics/{channel}', [AnalyticsController::class, 'show'])
                ->whereIn('channel', ['google', 'tiktok', 'meta'])
                ->name('analytics.show');
        });

        Route::get('manager-activity', [ManagerActivityController::class, 'index'])
            ->middleware('admin.permission:'.AdminPermissions::MANAGER_ACTIVITY_VIEW)
            ->name('manager-activity.index');

        Route::resource('categories', CategoryController::class)
            ->except(['show'])
            ->middleware('admin.permission:admin.categories.manage');
        Route::resource('attributes', ProductAttributeController::class)
            ->parameters(['attributes' => 'attribute'])
            ->except(['show'])
            ->middleware('admin.permission:admin.attributes.manage');
        Route::resource('color-groups', ProductColorGroupController::class)
            ->parameters(['color-groups' => 'color_group'])
            ->except(['show'])
            ->middleware('admin.permission:admin.color_groups.manage');
        Route::resource('size-charts', SizeChartController::class)
            ->parameters(['size-charts' => 'size_chart'])
            ->except(['show'])
            ->middleware('admin.permission:admin.size_charts.manage');

        Route::middleware('admin.permission:admin.reviews.manage')->group(function (): void {
            Route::patch('reviews/{review}/approve', [ReviewController::class, 'approve'])
                ->name('reviews.approve');
            Route::patch('reviews/{review}/reject', [ReviewController::class, 'reject'])
                ->name('reviews.reject');
            Route::resource('reviews', ReviewController::class)
                ->except(['show']);
        });

        Route::get('orders', [OrderController::class, 'index'])
            ->middleware('admin.permission:admin.orders.view')
            ->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])
            ->middleware('admin.permission:admin.orders.view')
            ->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->middleware('admin.permission:admin.orders.manage')
            ->name('orders.status');
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])
            ->middleware('admin.permission:admin.orders.manage')
            ->name('orders.destroy');

        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('admin.permission:admin.customers.view')
            ->name('customers.index');

        Route::resource('banners', BannerController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('admin.permission:'.AdminPermissions::SITE_STRUCTURE_MANAGE);
        Route::resource('pages', ContentPageController::class)
            ->except(['show'])
            ->middleware('admin.permission:'.AdminPermissions::SITE_STRUCTURE_MANAGE);

        $paymentDeliveryViewPermissions = implode('|', [
            AdminPermissions::DELIVERY_METHODS_VIEW,
            AdminPermissions::DELIVERY_METHODS_MANAGE,
            AdminPermissions::PAYMENT_METHODS_VIEW,
            AdminPermissions::PAYMENT_METHODS_MANAGE,
            AdminPermissions::DELIVERY_TARIFFS_VIEW,
            AdminPermissions::DELIVERY_TARIFFS_MANAGE,
            AdminPermissions::PAYMENT_TRANSACTIONS_VIEW,
        ]);

        Route::middleware('admin.permission:'.$paymentDeliveryViewPermissions)->group(function (): void {
            Route::get('payment-delivery', [PaymentDeliveryController::class, 'index'])
                ->name('payment-delivery.index');
            Route::get('payment-delivery/{section}', [PaymentDeliveryController::class, 'show'])
                ->whereIn('section', ['delivery-methods', 'payment-methods', 'tariffs', 'transactions'])
                ->name('payment-delivery.show');
        });

        Route::middleware('admin.permission:'.AdminPermissions::DELIVERY_METHODS_MANAGE)->group(function (): void {
            Route::post('payment-delivery/delivery-methods', [PaymentDeliveryController::class, 'storeDeliveryMethod'])
                ->name('payment-delivery.delivery-methods.store');
            Route::put('payment-delivery/delivery-methods/{deliveryMethod}', [PaymentDeliveryController::class, 'updateDeliveryMethod'])
                ->name('payment-delivery.delivery-methods.update');
            Route::delete('payment-delivery/delivery-methods/{deliveryMethod}', [PaymentDeliveryController::class, 'destroyDeliveryMethod'])
                ->name('payment-delivery.delivery-methods.destroy');
        });

        Route::middleware('admin.permission:'.AdminPermissions::PAYMENT_METHODS_MANAGE)->group(function (): void {
            Route::post('payment-delivery/payment-methods', [PaymentDeliveryController::class, 'storePaymentMethod'])
                ->name('payment-delivery.payment-methods.store');
            Route::put('payment-delivery/payment-methods/{paymentMethod}', [PaymentDeliveryController::class, 'updatePaymentMethod'])
                ->name('payment-delivery.payment-methods.update');
            Route::delete('payment-delivery/payment-methods/{paymentMethod}', [PaymentDeliveryController::class, 'destroyPaymentMethod'])
                ->name('payment-delivery.payment-methods.destroy');
        });

        Route::middleware('admin.permission:'.AdminPermissions::DELIVERY_TARIFFS_MANAGE)->group(function (): void {
            Route::post('payment-delivery/tariffs', [PaymentDeliveryController::class, 'storeTariff'])
                ->name('payment-delivery.tariffs.store');
            Route::put('payment-delivery/tariffs/{tariff}', [PaymentDeliveryController::class, 'updateTariff'])
                ->name('payment-delivery.tariffs.update');
            Route::delete('payment-delivery/tariffs/{tariff}', [PaymentDeliveryController::class, 'destroyTariff'])
                ->name('payment-delivery.tariffs.destroy');
        });

        Route::middleware('admin.permission:admin.settings.nova_poshta.manage')->group(function (): void {
            Route::get('settings/nova-poshta', [NovaPoshtaSettingsController::class, 'edit'])
                ->name('settings.nova-poshta.edit');
            Route::put('settings/nova-poshta', [NovaPoshtaSettingsController::class, 'update'])
                ->name('settings.nova-poshta.update');
            Route::post('settings/nova-poshta/sync-sender', [NovaPoshtaSettingsController::class, 'syncSender'])
                ->name('settings.nova-poshta.sync-sender');
        });

        $siteSettingsPermissions = implode('|', [
            AdminPermissions::SETTINGS_STORE_MANAGE,
            AdminPermissions::SETTINGS_CHECKOUT_MANAGE,
            AdminPermissions::SETTINGS_INTEGRATIONS_MANAGE,
            AdminPermissions::SETTINGS_PAYMENTS_MANAGE,
            AdminPermissions::SETTINGS_SECURITY_MANAGE,
            AdminPermissions::SETTINGS_SYSTEM_MANAGE,
        ]);

        Route::middleware('admin.permission:'.$siteSettingsPermissions)->group(function (): void {
            Route::get('settings/site', [SiteSettingsController::class, 'index'])
                ->name('settings.site.index');
            Route::get('settings/site/{section}', [SiteSettingsController::class, 'show'])
                ->whereIn('section', ['store', 'checkout', 'integrations', 'payments', 'security', 'system'])
                ->name('settings.site.show');
            Route::put('settings/site/{section}', [SiteSettingsController::class, 'update'])
                ->whereIn('section', ['store', 'checkout', 'integrations', 'payments', 'security', 'system'])
                ->name('settings.site.update');
        });

        Route::middleware('admin.permission:admin.settings.tracking.manage')->group(function (): void {
            Route::get('settings/tracking', [TrackingSettingsController::class, 'index'])
                ->name('settings.tracking.index');
            Route::get('settings/tracking/{channel}', [TrackingSettingsController::class, 'show'])
                ->whereIn('channel', ['google', 'tiktok', 'meta'])
                ->name('settings.tracking.show');
            Route::put('settings/tracking/{channel}', [TrackingSettingsController::class, 'update'])
                ->whereIn('channel', ['google', 'tiktok', 'meta'])
                ->name('settings.tracking.update');
        });

        Route::middleware('admin.permission:admin.site_structure.manage')->group(function (): void {
            Route::redirect('site-structure', '/admin/site-structure/main')
                ->name('site-structure.index');
            Route::prefix('site-structure/{menu}')
                ->whereIn('menu', ['main', 'utility', 'footer', 'mobile'])
                ->name('site-structure.')
                ->group(function (): void {
                    Route::get('/', [SiteStructureController::class, 'show'])->name('show');
                    Route::post('items', [SiteStructureController::class, 'store'])->name('items.store');
                    Route::put('items/{item}', [SiteStructureController::class, 'update'])->name('items.update');
                    Route::delete('items/{item}', [SiteStructureController::class, 'destroy'])->name('items.destroy');
                    Route::post('reorder', [SiteStructureController::class, 'reorder'])->name('reorder');
                });
        });

        $seoPermissions = implode('|', [
            AdminPermissions::SEO_AUDIT_VIEW,
            AdminPermissions::SEO_META_MANAGE,
            AdminPermissions::SEO_SCHEMA_MANAGE,
            AdminPermissions::SEO_REDIRECTS_MANAGE,
            AdminPermissions::SEO_INDEXING_MANAGE,
            AdminPermissions::SEO_SITEMAP_MANAGE,
            AdminPermissions::SEO_FILTER_SEO_MANAGE,
        ]);

        Route::middleware('admin.permission:'.$seoPermissions)->group(function (): void {
            Route::get('seo', [SeoController::class, 'index'])->name('seo.index');
            Route::get('seo/{section}', [SeoController::class, 'show'])
                ->whereIn('section', ['overview', 'meta', 'schema', 'redirects', 'indexing', 'sitemap', 'filter-seo'])
                ->name('seo.show');
        });

        Route::middleware('admin.permission:'.AdminPermissions::SEO_META_MANAGE)->group(function (): void {
            Route::put('seo/meta', [SeoController::class, 'updateMeta'])->name('seo.meta.update');
        });

        Route::middleware('admin.permission:'.AdminPermissions::SEO_SCHEMA_MANAGE)->group(function (): void {
            Route::put('seo/schema', [SeoController::class, 'updateSchema'])->name('seo.schema.update');
        });

        Route::middleware('admin.permission:'.AdminPermissions::SEO_REDIRECTS_MANAGE)->group(function (): void {
            Route::post('seo/redirects', [SeoController::class, 'storeRedirect'])->name('seo.redirects.store');
            Route::put('seo/redirects/{redirect}', [SeoController::class, 'updateRedirect'])->name('seo.redirects.update');
            Route::delete('seo/redirects/{redirect}', [SeoController::class, 'destroyRedirect'])->name('seo.redirects.destroy');
        });

        Route::middleware('admin.permission:'.AdminPermissions::SEO_INDEXING_MANAGE)->group(function (): void {
            Route::put('seo/indexing', [SeoController::class, 'updateIndexing'])->name('seo.indexing.update');
            Route::post('seo/indexing-rules', [SeoController::class, 'storeIndexingRule'])->name('seo.indexing-rules.store');
            Route::put('seo/indexing-rules/{rule}', [SeoController::class, 'updateIndexingRule'])->name('seo.indexing-rules.update');
            Route::delete('seo/indexing-rules/{rule}', [SeoController::class, 'destroyIndexingRule'])->name('seo.indexing-rules.destroy');
        });

        Route::middleware('admin.permission:'.AdminPermissions::SEO_SITEMAP_MANAGE)->group(function (): void {
            Route::post('seo/sitemap/regenerate', [SeoController::class, 'regenerateSitemap'])->name('seo.sitemap.regenerate');
        });

        Route::middleware('admin.permission:'.AdminPermissions::SEO_FILTER_SEO_MANAGE)->group(function (): void {
            Route::post('seo/filter-pages', [SeoController::class, 'storeFilterPage'])->name('seo.filter-pages.store');
            Route::put('seo/filter-pages/{page}', [SeoController::class, 'updateFilterPage'])->name('seo.filter-pages.update');
            Route::delete('seo/filter-pages/{page}', [SeoController::class, 'destroyFilterPage'])->name('seo.filter-pages.destroy');
        });

        Route::middleware('admin.permission:admin.roles.manage')->group(function (): void {
            Route::get('roles', [RolePermissionController::class, 'index'])->name('roles.index');
            Route::put('roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
            Route::post('roles/staff', [RolePermissionController::class, 'storeStaff'])->name('roles.staff.store');
            Route::patch('roles/staff/{user}', [RolePermissionController::class, 'updateStaff'])->name('roles.staff.update');
        });

        Route::post('system/cache/clear', [SystemCacheController::class, 'clear'])
            ->middleware('admin.permission:admin.system.cache.clear')
            ->name('system.cache.clear');
    });
