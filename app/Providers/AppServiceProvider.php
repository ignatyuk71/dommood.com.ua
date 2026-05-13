<?php

namespace App\Providers;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\DeliveryMethod;
use App\Models\DeliveryTariff;
use App\Models\FilterSeoPage;
use App\Models\PaymentMethod;
use App\Models\ProductAttribute;
use App\Models\ProductColorGroup;
use App\Models\ProductFeedConfig;
use App\Models\Review;
use App\Models\SeoIndexingRule;
use App\Models\SeoRedirect;
use App\Models\SeoSetting;
use App\Models\SeoTemplate;
use App\Models\ShippingProviderSetting;
use App\Models\SizeChart;
use App\Models\User;
use App\Observers\AdminActivityObserver;
use App\Services\Shipping\NovaPoshtaApi;
use App\Support\AdminPermissions;
use App\Services\Storefront\CartService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NovaPoshtaApi::class, function (): NovaPoshtaApi {
            [$settings, $isActive] = $this->novaPoshtaSettings();

            return new NovaPoshtaApi(
                apiKey: $settings['api_key'] ?? config('services.nova_poshta.api_key'),
                apiUrl: $settings['api_url'] ?? config('services.nova_poshta.api_url', 'https://api.novaposhta.ua/v2.0/json/'),
                senderCityRef: $settings['sender_city_ref'] ?? config('services.nova_poshta.sender_city_ref'),
                senderWarehouseRef: $settings['sender_warehouse_ref'] ?? config('services.nova_poshta.sender_warehouse_ref'),
                defaultWeight: (float) ($settings['default_weight'] ?? config('services.nova_poshta.default_weight', 1)),
                active: $isActive,
            );
        });
    }

    private function novaPoshtaSettings(): array
    {
        try {
            $provider = ShippingProviderSetting::query()
                ->where('code', 'nova_poshta')
                ->first();
        } catch (Throwable) {
            return [[], true];
        }

        if (! $provider) {
            return [[], true];
        }

        return [$provider->settings ?? [], (bool) $provider->is_active];
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAdminActivityObservers();
        $this->registerStorefrontViewData();

        Gate::before(function (User $user, string $ability): ?bool {
            if (! str_starts_with($ability, 'admin.')) {
                return null;
            }

            try {
                app(SyncAdminRolesAndPermissions::class)->assignLegacyRole($user);
            } catch (Throwable) {
                return in_array($ability, AdminPermissions::defaultsForRole($user->role), true) ?: null;
            }

            if ($user->hasRole('admin') || $user->role === 'admin') {
                return true;
            }

            if ($user->hasPermissionTo($ability)) {
                return true;
            }

            return in_array($ability, AdminPermissions::defaultsForRole($user->role), true) ?: null;
        });

        Vite::prefetch(concurrency: 3);
    }

    private function registerStorefrontViewData(): void
    {
        View::composer('storefront.*', function ($view): void {
            $request = request();

            if (! $request->attributes->has('storefront_header_cart_summary')) {
                try {
                    $cartSummary = app(CartService::class)->summaryForRequest($request);
                } catch (Throwable) {
                    $cartSummary = CartService::emptySummary();
                }

                $request->attributes->set('storefront_header_cart_summary', $cartSummary);
            }

            $view->with('headerCartSummary', $request->attributes->get('storefront_header_cart_summary'));
        });
    }

    private function registerAdminActivityObservers(): void
    {
        foreach ([
            Category::class,
            ProductAttribute::class,
            AttributeValue::class,
            ProductColorGroup::class,
            SizeChart::class,
            ProductFeedConfig::class,
            Review::class,
            DeliveryMethod::class,
            PaymentMethod::class,
            DeliveryTariff::class,
            SeoRedirect::class,
            SeoIndexingRule::class,
            FilterSeoPage::class,
            SeoSetting::class,
            SeoTemplate::class,
            ShippingProviderSetting::class,
            User::class,
        ] as $model) {
            $model::observe(AdminActivityObserver::class);
        }
    }
}
