<?php

namespace App\Http\Middleware;

use App\Services\AdminActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LogAdminModuleVisit
{
    private const THROTTLE_MINUTES = 20;

    private const SECTION_LABELS = [
        'delivery-methods' => 'Методи доставки',
        'payment-methods' => 'Методи оплати',
        'tariffs' => 'Тарифи',
        'transactions' => 'Транзакції',
        'main' => 'Головне меню',
        'footer' => 'Footer меню',
        'mobile' => 'Мобільне меню',
        'overview' => 'Огляд',
        'meta' => 'Meta & Templates',
        'schema' => 'Schema',
        'redirects' => 'Redirects',
        'indexing' => 'Indexing / Robots',
        'sitemap' => 'Sitemap',
        'filter-seo' => 'Filter SEO',
        'store' => 'Магазин',
        'checkout' => 'Checkout',
        'integrations' => 'Інтеграції',
        'payments' => 'Платежі',
        'security' => 'Безпека',
        'system' => 'Система',
        'google' => 'Google',
        'tiktok' => 'TikTok',
        'meta_channel' => 'Meta',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request, $response)) {
            return $response;
        }

        $module = $this->moduleFor($request);

        if (! $module || ! $this->reserveLogSlot($request, $module)) {
            return $response;
        }

        app(AdminActivityLogger::class)->log(
            $request,
            'admin.module_viewed',
            newValues: [
                'module' => $module['label'],
                'section' => $module['section_label'],
                'route' => $request->route()?->getName(),
                'path' => '/'.$request->path(),
            ],
            description: 'Менеджер відкрив розділ: '.$module['label'].($module['section_label'] ? ' / '.$module['section_label'] : ''),
        );

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $request->user()) {
            return false;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        $routeName = (string) $request->route()?->getName();

        return $routeName === 'dashboard' || str_starts_with($routeName, 'admin.');
    }

    private function moduleFor(Request $request): ?array
    {
        $routeName = (string) $request->route()?->getName();
        $section = $this->routeContext($request);

        $modules = [
            'dashboard' => ['key' => 'dashboard', 'label' => 'Дашборд'],
            'admin.products.' => ['key' => 'products', 'label' => 'Товари'],
            'admin.product-feeds.' => ['key' => 'product-feeds', 'label' => 'Product Feeds'],
            'admin.analytics.' => ['key' => 'analytics', 'label' => 'Аналітика'],
            'admin.manager-activity.' => ['key' => 'manager-activity', 'label' => 'Аналіз менеджерів'],
            'admin.categories.' => ['key' => 'categories', 'label' => 'Категорії'],
            'admin.attributes.' => ['key' => 'attributes', 'label' => 'Характеристики'],
            'admin.color-groups.' => ['key' => 'color-groups', 'label' => 'Групи кольорів'],
            'admin.size-charts.' => ['key' => 'size-charts', 'label' => 'Розмірні сітки'],
            'admin.reviews.' => ['key' => 'reviews', 'label' => 'Відгуки'],
            'admin.orders.' => ['key' => 'orders', 'label' => 'Замовлення'],
            'admin.customers.' => ['key' => 'customers', 'label' => 'Клієнти'],
            'admin.payment-delivery.' => ['key' => 'payment-delivery', 'label' => 'Оплата та доставка'],
            'admin.settings.nova-poshta.' => ['key' => 'nova-poshta', 'label' => 'Нова пошта'],
            'admin.settings.site.' => ['key' => 'site-settings', 'label' => 'Налаштування сайту'],
            'admin.settings.tracking.' => ['key' => 'tracking', 'label' => 'Tracking'],
            'admin.site-structure.' => ['key' => 'site-structure', 'label' => 'Структура сайту'],
            'admin.seo.' => ['key' => 'seo', 'label' => 'SEO-оптимізація'],
            'admin.roles.' => ['key' => 'roles', 'label' => 'Ролі та персонал'],
        ];

        foreach ($modules as $prefix => $module) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix)) {
                return [
                    ...$module,
                    'context' => $section['value'],
                    'section_label' => $section['label'],
                ];
            }
        }

        return null;
    }

    private function routeContext(Request $request): array
    {
        $value = (string) (
            $request->route('section')
            ?? $request->route('menu')
            ?? $request->route('channel')
            ?? ''
        );

        $labelKey = $value === 'meta' && str_starts_with((string) $request->route()?->getName(), 'admin.settings.tracking.')
            ? 'meta_channel'
            : $value;

        return [
            'value' => $value,
            'label' => self::SECTION_LABELS[$labelKey] ?? ($value !== '' ? $value : null),
        ];
    }

    private function reserveLogSlot(Request $request, array $module): bool
    {
        $key = implode(':', [
            'admin_module_visit',
            $request->user()?->id,
            $module['key'],
            $module['context'] ?: 'main',
        ]);

        return Cache::add($key, true, now()->addMinutes(self::THROTTLE_MINUTES));
    }
}
