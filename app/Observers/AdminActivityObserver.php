<?php

namespace App\Observers;

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
use App\Services\AdminActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminActivityObserver
{
    private const MODEL_CONFIG = [
        Category::class => [
            'prefix' => 'catalog.category',
            'name' => 'категорію',
            'fields' => ['parent_id', 'name', 'slug', 'description', 'image_path', 'is_active', 'sort_order', 'meta_title', 'meta_description', 'seo_text'],
        ],
        ProductAttribute::class => [
            'prefix' => 'catalog.attribute',
            'name' => 'характеристику',
            'fields' => ['name', 'slug', 'type', 'is_filterable', 'is_variant_option', 'sort_order'],
        ],
        AttributeValue::class => [
            'prefix' => 'catalog.attribute_value',
            'name' => 'значення характеристики',
            'fields' => ['attribute_id', 'value', 'slug', 'color_hex', 'sort_order'],
        ],
        ProductColorGroup::class => [
            'prefix' => 'catalog.color_group',
            'name' => 'групу кольорів',
            'fields' => ['name', 'code', 'description', 'is_active', 'sort_order'],
        ],
        SizeChart::class => [
            'prefix' => 'catalog.size_chart',
            'name' => 'розмірну сітку',
            'fields' => ['title', 'code', 'description', 'image_path', 'is_active', 'sort_order'],
        ],
        ProductFeedConfig::class => [
            'prefix' => 'catalog.product_feed',
            'name' => 'налаштування товарного фіда',
            'fields' => ['product_id', 'channel', 'is_enabled', 'brand', 'google_product_category', 'custom_title', 'custom_description', 'google_gender', 'google_age_group', 'google_material', 'google_pattern', 'google_size_system', 'google_size_types', 'google_is_bundle', 'google_item_group_id', 'google_product_highlights', 'google_product_details', 'custom_label_0', 'custom_label_1', 'custom_label_2', 'custom_label_3', 'custom_label_4'],
        ],
        Review::class => [
            'prefix' => 'catalog.review',
            'name' => 'відгук',
            'fields' => ['product_id', 'customer_id', 'author_name', 'author_email', 'rating', 'title', 'status', 'is_verified_buyer', 'source', 'moderation_note', 'admin_reply', 'published_at', 'moderated_by', 'moderated_at', 'replied_by', 'replied_at'],
        ],
        DeliveryMethod::class => [
            'prefix' => 'payment_delivery.delivery_method',
            'name' => 'метод доставки',
            'fields' => ['name', 'code', 'provider', 'type', 'description', 'base_price_cents', 'free_from_cents', 'is_active', 'sort_order'],
        ],
        PaymentMethod::class => [
            'prefix' => 'payment_delivery.payment_method',
            'name' => 'метод оплати',
            'fields' => ['name', 'code', 'type', 'description', 'fee_percent', 'fixed_fee_cents', 'is_active', 'sort_order'],
        ],
        DeliveryTariff::class => [
            'prefix' => 'payment_delivery.tariff',
            'name' => 'тариф доставки',
            'fields' => ['delivery_method_id', 'name', 'code', 'region', 'city', 'min_order_cents', 'max_order_cents', 'price_cents', 'free_from_cents', 'is_active', 'sort_order'],
        ],
        SeoRedirect::class => [
            'prefix' => 'seo.redirect',
            'name' => 'SEO редірект',
            'fields' => ['source_path', 'target_url', 'status_code', 'preserve_query', 'is_active', 'notes'],
        ],
        SeoIndexingRule::class => [
            'prefix' => 'seo.indexing_rule',
            'name' => 'правило індексації',
            'fields' => ['name', 'pattern', 'pattern_type', 'robots_directive', 'meta_robots', 'canonical_url', 'is_active', 'sort_order'],
        ],
        FilterSeoPage::class => [
            'prefix' => 'seo.filter_page',
            'name' => 'SEO сторінку фільтра',
            'fields' => ['category_id', 'slug', 'filters', 'h1', 'title', 'meta_title', 'meta_description', 'canonical_url', 'is_indexable', 'is_active', 'sort_order'],
        ],
        SeoSetting::class => [
            'prefix' => 'seo.setting',
            'name' => 'SEO налаштування',
            'fields' => ['section', 'key', 'value'],
        ],
        SeoTemplate::class => [
            'prefix' => 'seo.template',
            'name' => 'SEO шаблон',
            'fields' => ['entity_type', 'field', 'template', 'is_active', 'sort_order'],
        ],
        ShippingProviderSetting::class => [
            'prefix' => 'settings.shipping_provider',
            'name' => 'налаштування перевізника',
            'fields' => ['code', 'name', 'is_active', 'sort_order'],
        ],
        User::class => [
            'prefix' => 'roles.staff',
            'name' => 'працівника',
            'fields' => ['name', 'email', 'phone', 'role', 'is_active'],
        ],
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action): void
    {
        $request = $this->adminRequest();
        $config = self::MODEL_CONFIG[$model::class] ?? null;

        if (! $request || ! $config) {
            return;
        }

        [$oldValues, $newValues] = $this->valuesForAction($model, $action, $config['fields']);

        if ($action === 'updated' && $oldValues === [] && $newValues === []) {
            return;
        }

        app(AdminActivityLogger::class)->log(
            $request,
            $config['prefix'].'.'.$action,
            $model,
            oldValues: $oldValues,
            newValues: $newValues,
            description: $this->description($config['name'], $action),
        );
    }

    private function adminRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        $routeName = (string) $request->route()?->getName();

        if (! $request->user() || ! str_starts_with($routeName, 'admin.')) {
            return null;
        }

        return $request;
    }

    private function valuesForAction(Model $model, string $action, array $fields): array
    {
        if ($action === 'created') {
            return [[], $this->snapshot($model, $fields)];
        }

        if ($action === 'deleted') {
            return [$this->snapshot($model, $fields, original: true), ['deleted' => true]];
        }

        $changedFields = collect(array_keys($model->getChanges()))
            ->reject(fn (string $field): bool => in_array($field, ['created_at', 'updated_at', 'deleted_at'], true))
            ->values()
            ->all();
        $visibleChangedFields = array_values(array_intersect($changedFields, $fields));

        if ($changedFields === []) {
            return [[], []];
        }

        if ($visibleChangedFields === []) {
            return [[], [
                'updated' => true,
                'changed_fields' => $changedFields,
            ]];
        }

        return [
            $this->snapshot($model, $visibleChangedFields, original: true),
            $this->snapshot($model, $visibleChangedFields),
        ];
    }

    private function snapshot(Model $model, array $fields, bool $original = false): array
    {
        $attributes = $original ? $model->getOriginal() : $model->getAttributes();

        return collect($fields)
            ->filter(fn (string $field): bool => array_key_exists($field, $attributes))
            ->mapWithKeys(fn (string $field): array => [
                $field => $original ? $model->getOriginal($field) : $model->getAttribute($field),
            ])
            ->all();
    }

    private function description(string $modelName, string $action): string
    {
        $verb = match ($action) {
            'created' => 'створив',
            'updated' => 'оновив',
            'deleted' => 'видалив',
            default => 'змінив',
        };

        return "Менеджер {$verb} {$modelName}";
    }
}
