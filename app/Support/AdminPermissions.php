<?php

namespace App\Support;

final class AdminPermissions
{
    public const DASHBOARD_VIEW = 'admin.dashboard.view';

    public const ANALYTICS_VIEW = 'admin.analytics.view';

    public const MANAGER_ACTIVITY_VIEW = 'admin.manager_activity.view';

    public const PRODUCTS_VIEW = 'admin.products.view';

    public const PRODUCTS_MANAGE = 'admin.products.manage';

    public const PRODUCT_FEEDS_VIEW = 'admin.product_feeds.view';

    public const PRODUCT_FEEDS_MANAGE = 'admin.product_feeds.manage';

    public const CATEGORIES_MANAGE = 'admin.categories.manage';

    public const ATTRIBUTES_MANAGE = 'admin.attributes.manage';

    public const COLOR_GROUPS_MANAGE = 'admin.color_groups.manage';

    public const SIZE_CHARTS_MANAGE = 'admin.size_charts.manage';

    public const REVIEWS_MANAGE = 'admin.reviews.manage';

    public const ORDERS_VIEW = 'admin.orders.view';

    public const ORDERS_MANAGE = 'admin.orders.manage';

    public const DELIVERY_METHODS_VIEW = 'admin.payment_delivery.delivery_methods.view';

    public const DELIVERY_METHODS_MANAGE = 'admin.payment_delivery.delivery_methods.manage';

    public const PAYMENT_METHODS_VIEW = 'admin.payment_delivery.payment_methods.view';

    public const PAYMENT_METHODS_MANAGE = 'admin.payment_delivery.payment_methods.manage';

    public const DELIVERY_TARIFFS_VIEW = 'admin.payment_delivery.tariffs.view';

    public const DELIVERY_TARIFFS_MANAGE = 'admin.payment_delivery.tariffs.manage';

    public const PAYMENT_TRANSACTIONS_VIEW = 'admin.payment_delivery.transactions.view';

    public const CUSTOMERS_VIEW = 'admin.customers.view';

    public const SITE_STRUCTURE_MANAGE = 'admin.site_structure.manage';

    public const SEO_AUDIT_VIEW = 'admin.seo.audit.view';

    public const SEO_META_MANAGE = 'admin.seo.meta.manage';

    public const SEO_SCHEMA_MANAGE = 'admin.seo.schema.manage';

    public const SEO_REDIRECTS_MANAGE = 'admin.seo.redirects.manage';

    public const SEO_INDEXING_MANAGE = 'admin.seo.indexing.manage';

    public const SEO_SITEMAP_MANAGE = 'admin.seo.sitemap.manage';

    public const SEO_FILTER_SEO_MANAGE = 'admin.seo.filter_seo.manage';

    public const SETTINGS_STORE_MANAGE = 'admin.settings.store.manage';

    public const SETTINGS_CHECKOUT_MANAGE = 'admin.settings.checkout.manage';

    public const SETTINGS_INTEGRATIONS_MANAGE = 'admin.settings.integrations.manage';

    public const SETTINGS_PAYMENTS_MANAGE = 'admin.settings.payments.manage';

    public const SETTINGS_SECURITY_MANAGE = 'admin.settings.security.manage';

    public const SETTINGS_SYSTEM_MANAGE = 'admin.settings.system.manage';

    public const SETTINGS_TRACKING_MANAGE = 'admin.settings.tracking.manage';

    public const SETTINGS_NOVA_POSHTA_MANAGE = 'admin.settings.nova_poshta.manage';

    public const ROLES_MANAGE = 'admin.roles.manage';

    public const SYSTEM_CACHE_CLEAR = 'admin.system.cache.clear';

    public static function groups(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Дашборд',
                'permissions' => [
                    self::DASHBOARD_VIEW => 'Перегляд дашборду',
                ],
            ],
            [
                'key' => 'analytics',
                'label' => 'Аналітика',
                'permissions' => [
                    self::ANALYTICS_VIEW => 'Перегляд аналітики',
                    self::MANAGER_ACTIVITY_VIEW => 'Аналіз менеджерів',
                ],
            ],
            [
                'key' => 'catalog',
                'label' => 'Каталог',
                'permissions' => [
                    self::PRODUCTS_VIEW => 'Перегляд товарів',
                    self::PRODUCTS_MANAGE => 'Керування товарами',
                    self::PRODUCT_FEEDS_VIEW => 'Перегляд Product Feeds',
                    self::PRODUCT_FEEDS_MANAGE => 'Керування Product Feeds',
                    self::CATEGORIES_MANAGE => 'Керування категоріями',
                    self::ATTRIBUTES_MANAGE => 'Керування характеристиками',
                    self::COLOR_GROUPS_MANAGE => 'Керування групами кольорів',
                    self::SIZE_CHARTS_MANAGE => 'Керування розмірними сітками',
                    self::REVIEWS_MANAGE => 'Модерація відгуків',
                ],
            ],
            [
                'key' => 'orders',
                'label' => 'Замовлення',
                'permissions' => [
                    self::ORDERS_VIEW => 'Перегляд замовлень',
                    self::ORDERS_MANAGE => 'Зміна статусів і видалення',
                ],
            ],
            [
                'key' => 'operations',
                'label' => 'Операції',
                'permissions' => [
                    self::DELIVERY_METHODS_VIEW => 'Перегляд методів доставки',
                    self::DELIVERY_METHODS_MANAGE => 'Керування методами доставки',
                    self::PAYMENT_METHODS_VIEW => 'Перегляд методів оплати',
                    self::PAYMENT_METHODS_MANAGE => 'Керування методами оплати',
                    self::DELIVERY_TARIFFS_VIEW => 'Перегляд тарифів доставки',
                    self::DELIVERY_TARIFFS_MANAGE => 'Керування тарифами доставки',
                    self::PAYMENT_TRANSACTIONS_VIEW => 'Перегляд платіжних транзакцій',
                    self::CUSTOMERS_VIEW => 'Перегляд клієнтів',
                ],
            ],
            [
                'key' => 'seo',
                'label' => 'SEO',
                'permissions' => [
                    self::SEO_AUDIT_VIEW => 'SEO аудит і overview',
                    self::SEO_META_MANAGE => 'Meta & Templates',
                    self::SEO_SCHEMA_MANAGE => 'Schema',
                    self::SEO_REDIRECTS_MANAGE => 'Redirects',
                    self::SEO_INDEXING_MANAGE => 'Indexing / Robots',
                    self::SEO_SITEMAP_MANAGE => 'Sitemap',
                    self::SEO_FILTER_SEO_MANAGE => 'Filter SEO',
                ],
            ],
            [
                'key' => 'site',
                'label' => 'Сайт і налаштування',
                'permissions' => [
                    self::SITE_STRUCTURE_MANAGE => 'Структура сайту',
                    self::SETTINGS_STORE_MANAGE => 'Налаштування магазину',
                    self::SETTINGS_CHECKOUT_MANAGE => 'Налаштування checkout',
                    self::SETTINGS_INTEGRATIONS_MANAGE => 'Інтеграції',
                    self::SETTINGS_PAYMENTS_MANAGE => 'Платіжні підключення',
                    self::SETTINGS_SECURITY_MANAGE => 'Безпека',
                    self::SETTINGS_SYSTEM_MANAGE => 'Системні налаштування',
                    self::SETTINGS_TRACKING_MANAGE => 'Tracking підключення',
                    self::SETTINGS_NOVA_POSHTA_MANAGE => 'API Нової пошти',
                    self::ROLES_MANAGE => 'Ролі та доступи',
                    self::SYSTEM_CACHE_CLEAR => 'Очищення кешу',
                ],
            ],
        ];
    }

    public static function all(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group): array => array_keys($group['permissions']))
            ->values()
            ->all();
    }

    public static function defaultsForRole(string $role): array
    {
        return match ($role) {
            'admin' => self::all(),
            'manager' => [
                self::DASHBOARD_VIEW,
                self::ORDERS_VIEW,
                self::ORDERS_MANAGE,
                self::CUSTOMERS_VIEW,
                self::PAYMENT_TRANSACTIONS_VIEW,
            ],
            default => [],
        };
    }

    public static function labels(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group): array => $group['permissions'])
            ->all();
    }
}
