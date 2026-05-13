<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductColorGroup;
use App\Models\Review;
use App\Models\SizeChart;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ManagerActivityController extends Controller
{
    private const EVENT_GROUPS = [
        'modules' => [
            'label' => 'Перегляди розділів',
            'events' => ['admin.module_viewed'],
        ],
        'logins' => [
            'label' => 'Входи',
            'events' => ['admin.login'],
        ],
        'orders' => [
            'label' => 'Замовлення',
            'events' => ['order.status_updated', 'order.deleted'],
        ],
        'products' => [
            'label' => 'Товари',
            'events' => [
                'product.created',
                'product.updated',
                'product.deleted',
                'product.duplicated',
                'product.quick_updated',
                'product.variant_updated',
                'product.variant_deleted',
            ],
        ],
        'site_structure' => [
            'label' => 'Структура сайту',
            'prefixes' => ['site_structure.'],
        ],
        'catalog' => [
            'label' => 'Каталог',
            'prefixes' => ['catalog.'],
        ],
        'payment_delivery' => [
            'label' => 'Оплата і доставка',
            'prefixes' => ['payment_delivery.'],
        ],
        'seo' => [
            'label' => 'SEO',
            'prefixes' => ['seo.'],
        ],
        'settings' => [
            'label' => 'Налаштування',
            'prefixes' => ['settings.'],
        ],
        'roles' => [
            'label' => 'Персонал',
            'prefixes' => ['roles.'],
        ],
        'system' => [
            'label' => 'Система',
            'prefixes' => ['system.'],
        ],
    ];

    private const EVENT_LABELS = [
        'admin.module_viewed' => 'Відкриття розділу',
        'admin.login' => 'Вхід в адмінку',
        'order.status_updated' => 'Зміна статусу замовлення',
        'order.deleted' => 'Видалення замовлення',
        'product.created' => 'Створення товару',
        'product.updated' => 'Оновлення товару',
        'product.deleted' => 'Видалення товару',
        'product.duplicated' => 'Дублювання товару',
        'product.quick_updated' => 'Швидке оновлення товару',
        'product.variant_updated' => 'Оновлення варіації',
        'product.variant_deleted' => 'Видалення варіації',
        'site_structure.menu_item_created' => 'Створення пункту меню',
        'site_structure.menu_item_updated' => 'Оновлення пункту меню',
        'site_structure.menu_item_deleted' => 'Видалення пункту меню',
        'site_structure.menu_reordered' => 'Зміна порядку меню',
        'settings.site_updated' => 'Оновлення налаштувань сайту',
        'settings.tracking_updated' => 'Оновлення tracking',
        'roles.permissions_updated' => 'Оновлення доступів ролі',
        'system.cache_cleared' => 'Очищення кешу',
        'seo.sitemap_regenerated' => 'Перегенерація sitemap',
    ];

    private const EVENT_ENTITY_LABELS = [
        'catalog.category' => 'категорії',
        'catalog.attribute' => 'характеристики',
        'catalog.attribute_value' => 'значення характеристики',
        'catalog.color_group' => 'групи кольорів',
        'catalog.size_chart' => 'розмірної сітки',
        'catalog.product_feed' => 'налаштувань Product Feed',
        'catalog.review' => 'відгуку',
        'payment_delivery.delivery_method' => 'методу доставки',
        'payment_delivery.payment_method' => 'методу оплати',
        'payment_delivery.tariff' => 'тарифу доставки',
        'seo.redirect' => 'SEO редіректу',
        'seo.indexing_rule' => 'правила індексації',
        'seo.filter_page' => 'SEO сторінки фільтра',
        'seo.setting' => 'SEO налаштувань',
        'seo.template' => 'SEO шаблону',
        'settings.site' => 'налаштувань сайту',
        'settings.shipping_provider' => 'налаштувань перевізника',
        'roles.staff' => 'працівника',
    ];

    private const EVENT_ACTION_LABELS = [
        'created' => 'Створення',
        'updated' => 'Оновлення',
        'deleted' => 'Видалення',
    ];

    private const FIELD_LABELS = [
        'module' => 'Розділ',
        'section' => 'Підрозділ',
        'route' => 'Route',
        'path' => 'URL',
        'name' => 'Назва',
        'title' => 'Назва',
        'sku' => 'SKU',
        'slug' => 'Slug',
        'code' => 'Код',
        'status' => 'Статус',
        'price_cents' => 'Ціна',
        'old_price_cents' => 'Стара ціна',
        'base_price_cents' => 'Базова ціна',
        'free_from_cents' => 'Безкоштовно від',
        'fixed_fee_cents' => 'Фіксована комісія',
        'min_order_cents' => 'Мін. замовлення',
        'max_order_cents' => 'Макс. замовлення',
        'stock_status' => 'Наявність',
        'primary_category_id' => 'Основна категорія',
        'category_id' => 'Категорія',
        'parent_id' => 'Батьківський пункт',
        'attribute_id' => 'Характеристика',
        'product_id' => 'Товар',
        'customer_id' => 'Клієнт',
        'published_at' => 'Опубліковано',
        'order_number' => 'Номер замовлення',
        'total_cents' => 'Сума',
        'payment_status' => 'Оплата',
        'payment_method' => 'Метод оплати',
        'delivery_method_id' => 'Метод доставки',
        'size' => 'Розмір',
        'stock_quantity' => 'Залишок',
        'is_active' => 'Активність',
        'is_filterable' => 'Фільтр',
        'is_variant_option' => 'Опція варіації',
        'is_verified_buyer' => 'Перевірений покупець',
        'is_indexable' => 'Індексується',
        'is_enabled' => 'Увімкнено',
        'source_product_id' => 'Джерело',
        'menu' => 'Меню',
        'type' => 'Тип',
        'url' => 'URL',
        'target' => 'Відкриття',
        'badge' => 'Бейдж',
        'sort_order' => 'Позиція',
        'structure' => 'Структура меню',
        'description' => 'Опис',
        'image_path' => 'Зображення',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
        'seo_text' => 'SEO текст',
        'value' => 'Значення',
        'color_hex' => 'Колір',
        'provider' => 'Провайдер',
        'fee_percent' => 'Комісія, %',
        'region' => 'Регіон',
        'city' => 'Місто',
        'source_path' => 'Звідки',
        'target_url' => 'Куди',
        'status_code' => 'HTTP статус',
        'preserve_query' => 'Зберігати query',
        'notes' => 'Нотатки',
        'pattern' => 'Патерн',
        'pattern_type' => 'Тип патерну',
        'robots_directive' => 'Robots directive',
        'meta_robots' => 'Meta robots',
        'canonical_url' => 'Canonical',
        'filters' => 'Фільтри',
        'h1' => 'H1',
        'key' => 'Ключ',
        'template' => 'Шаблон',
        'entity_type' => 'Тип сутності',
        'field' => 'Поле',
        'author_name' => 'Автор',
        'author_email' => 'Email автора',
        'rating' => 'Оцінка',
        'source' => 'Джерело',
        'moderation_note' => 'Нотатка модерації',
        'admin_reply' => 'Відповідь адміна',
        'moderated_by' => 'Модератор',
        'moderated_at' => 'Модеровано',
        'replied_by' => 'Відповів',
        'replied_at' => 'Час відповіді',
        'phone' => 'Телефон',
        'role' => 'Роль',
        'channel' => 'Канал',
        'brand' => 'Бренд',
        'google_product_category' => 'Google category',
        'custom_title' => 'Custom title',
        'custom_description' => 'Custom description',
        'google_gender' => 'Google gender',
        'google_age_group' => 'Google age group',
        'google_material' => 'Матеріал',
        'google_pattern' => 'Патерн товару',
        'google_size_system' => 'Size system',
        'google_size_types' => 'Size types',
        'google_is_bundle' => 'Bundle',
        'google_item_group_id' => 'Item group ID',
        'google_product_highlights' => 'Highlights',
        'google_product_details' => 'Details',
        'custom_label_0' => 'Custom label 0',
        'custom_label_1' => 'Custom label 1',
        'custom_label_2' => 'Custom label 2',
        'custom_label_3' => 'Custom label 3',
        'custom_label_4' => 'Custom label 4',
        'deleted' => 'Видалено',
        'updated' => 'Оновлено',
        'changed_fields' => 'Змінені поля',
        'permissions' => 'Доступи',
        'credentials_changed' => 'Ключі/токени',
        'settings_changed' => 'Налаштування',
        'status_changed' => 'Статус змінено',
        'mode_changed' => 'Режим змінено',
        'total_urls_count' => 'URL у sitemap',
    ];

    private const ORDER_STATUS_LABELS = [
        'new' => 'Нове',
        'confirmed' => 'Підтверджено',
        'processing' => 'В роботі',
        'shipped' => 'Відправлено',
        'completed' => 'Завершено',
        'cancelled' => 'Скасовано',
        'returned' => 'Повернення',
    ];

    private const PRODUCT_STATUS_LABELS = [
        Product::STATUS_DRAFT => 'Чернетка',
        Product::STATUS_ACTIVE => 'Активний',
        Product::STATUS_ARCHIVED => 'Архів',
    ];

    private const STOCK_STATUS_LABELS = [
        Product::STOCK_IN_STOCK => 'В наявності',
        Product::STOCK_OUT_OF_STOCK => 'Немає в наявності',
        Product::STOCK_PREORDER => 'Передзамовлення',
    ];

    public function index(Request $request): Response
    {
        $period = $this->dateRange($request);
        $filters = [
            'manager_id' => $request->integer('manager_id') ?: null,
            'event_group' => $request->string('event_group')->toString(),
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ];

        $baseQuery = $this->activityQuery($period['start'], $period['end'], $filters);

        $logs = (clone $baseQuery)
            ->with('user:id,name,email,role,last_login_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AdminActivityLog $log): array => $this->serializeLog($log));

        return Inertia::render('Admin/ManagerActivity/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'managers' => $this->managers(),
            'eventGroups' => $this->eventGroups(),
            'summary' => $this->summary($baseQuery),
            'managerStats' => $this->managerStats($baseQuery),
        ]);
    }

    private function activityQuery(CarbonImmutable $start, CarbonImmutable $end, array $filters): Builder
    {
        return AdminActivityLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['manager_id'], fn (Builder $query, int $managerId): Builder => $query->where('user_id', $managerId))
            ->when(
                isset(self::EVENT_GROUPS[$filters['event_group']]),
                fn (Builder $query): Builder => $this->applyEventGroup($query, self::EVENT_GROUPS[$filters['event_group']])
            );
    }

    private function applyEventGroup(Builder $query, array $group): Builder
    {
        return $query->where(function (Builder $inner) use ($group): void {
            $hasCondition = false;

            if (($group['events'] ?? []) !== []) {
                $inner->whereIn('event', $group['events']);
                $hasCondition = true;
            }

            foreach ($group['prefixes'] ?? [] as $prefix) {
                $method = $hasCondition ? 'orWhere' : 'where';
                $inner->{$method}('event', 'like', $prefix.'%');
                $hasCondition = true;
            }
        });
    }

    private function dateRange(Request $request): array
    {
        $today = CarbonImmutable::today();
        $start = $this->parseDate($request->string('date_from')->toString()) ?? $today->subDays(13);
        $end = $this->parseDate($request->string('date_to')->toString()) ?? $today;

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start->startOfDay(),
            'end' => $end->endOfDay(),
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ];
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function managers(): array
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->whereIn('role', ['admin', 'manager'])
                    ->orWhereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', ['admin', 'manager']));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'last_login_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $this->roleLabel($user->role),
                'last_login_at' => $user->last_login_at?->format('d.m.Y H:i'),
            ])
            ->values()
            ->all();
    }

    private function eventGroups(): array
    {
        return collect(self::EVENT_GROUPS)
            ->map(fn (array $group, string $key): array => [
                'value' => $key,
                'label' => $group['label'],
            ])
            ->values()
            ->prepend(['value' => '', 'label' => 'Усі дії'])
            ->all();
    }

    private function summary(Builder $baseQuery): array
    {
        $lastActivity = (clone $baseQuery)->max('created_at');

        return [
            'total_actions' => (clone $baseQuery)->count(),
            'active_managers' => (clone $baseQuery)->whereNotNull('user_id')->distinct()->count('user_id'),
            'logins_count' => (clone $baseQuery)->where('event', 'admin.login')->count(),
            'module_views_count' => (clone $baseQuery)->where('event', 'admin.module_viewed')->count(),
            'order_actions_count' => (clone $baseQuery)->where('event', 'like', 'order.%')->count(),
            'product_actions_count' => (clone $baseQuery)->where('event', 'like', 'product.%')->count(),
            'catalog_actions_count' => (clone $baseQuery)->where('event', 'like', 'catalog.%')->count(),
            'settings_actions_count' => (clone $baseQuery)->where(function (Builder $query): void {
                $query->where('event', 'like', 'settings.%')
                    ->orWhere('event', 'like', 'seo.%')
                    ->orWhere('event', 'like', 'payment_delivery.%');
            })->count(),
            'last_activity_at' => $lastActivity ? CarbonImmutable::parse($lastActivity)->format('d.m.Y H:i') : null,
        ];
    }

    private function managerStats(Builder $baseQuery): array
    {
        $rows = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->select('user_id')
            ->selectRaw('COUNT(*) as total_actions')
            ->selectRaw("SUM(CASE WHEN event = 'admin.login' THEN 1 ELSE 0 END) as logins_count")
            ->selectRaw("SUM(CASE WHEN event = 'admin.module_viewed' THEN 1 ELSE 0 END) as module_views_count")
            ->selectRaw("SUM(CASE WHEN event LIKE 'order.%' THEN 1 ELSE 0 END) as order_actions_count")
            ->selectRaw("SUM(CASE WHEN event LIKE 'product.%' THEN 1 ELSE 0 END) as product_actions_count")
            ->selectRaw("SUM(CASE WHEN event LIKE 'catalog.%' THEN 1 ELSE 0 END) as catalog_actions_count")
            ->selectRaw('MAX(created_at) as last_activity_at')
            ->groupBy('user_id')
            ->orderByDesc('total_actions')
            ->limit(8)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->all())
            ->get(['id', 'name', 'email', 'role'])
            ->keyBy('id');

        return $rows
            ->map(fn ($row): array => [
                'user_id' => (int) $row->user_id,
                'name' => $users->get($row->user_id)?->name ?? 'Користувач видалений',
                'email' => $users->get($row->user_id)?->email,
                'role_label' => $this->roleLabel($users->get($row->user_id)?->role),
                'total_actions' => (int) $row->total_actions,
                'logins_count' => (int) $row->logins_count,
                'module_views_count' => (int) $row->module_views_count,
                'order_actions_count' => (int) $row->order_actions_count,
                'product_actions_count' => (int) $row->product_actions_count,
                'catalog_actions_count' => (int) $row->catalog_actions_count,
                'last_activity_at' => $row->last_activity_at ? CarbonImmutable::parse($row->last_activity_at)->format('d.m.Y H:i') : null,
            ])
            ->values()
            ->all();
    }

    private function serializeLog(AdminActivityLog $log): array
    {
        $changes = $this->changes($log);

        return [
            'id' => $log->id,
            'event' => $log->event,
            'event_label' => $this->eventLabel($log->event),
            'event_group' => $this->eventGroup($log->event),
            'event_class' => $this->eventClass($log->event),
            'manager' => [
                'id' => $log->user?->id,
                'name' => $log->user?->name ?? 'Користувач видалений',
                'email' => $log->user?->email,
                'role_label' => $this->roleLabel($log->user?->role),
            ],
            'subject_label' => $log->subject_label,
            'subject_url' => $this->subjectUrl($log),
            'description' => $log->description,
            'changes' => $changes->take(5)->values()->all(),
            'changes_extra_count' => max(0, $changes->count() - 5),
            'metadata' => $log->metadata ?? [],
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->format('d.m.Y H:i'),
        ];
    }

    private function eventLabel(string $event): string
    {
        if (isset(self::EVENT_LABELS[$event])) {
            return self::EVENT_LABELS[$event];
        }

        $dotPosition = strrpos($event, '.');

        if ($dotPosition === false) {
            return $event;
        }

        $prefix = substr($event, 0, $dotPosition);
        $action = substr($event, $dotPosition + 1);

        if (isset(self::EVENT_ENTITY_LABELS[$prefix], self::EVENT_ACTION_LABELS[$action])) {
            return self::EVENT_ACTION_LABELS[$action].' '.self::EVENT_ENTITY_LABELS[$prefix];
        }

        return $event;
    }

    private function changes(AdminActivityLog $log): Collection
    {
        $oldValues = collect($log->old_values ?? []);
        $newValues = collect($log->new_values ?? []);
        $keys = $oldValues->keys()->merge($newValues->keys())->unique()->values();

        return $keys
            ->reject(fn ($key): bool => in_array($key, ['id'], true))
            ->map(function ($key) use ($oldValues, $newValues, $log): ?array {
                $key = (string) $key;
                $oldValue = $oldValues->get($key);
                $newValue = $newValues->get($key);

                if ($oldValues->has($key) && $newValues->has($key) && $oldValue === $newValue) {
                    return null;
                }

                return [
                    'field' => $key,
                    'label' => self::FIELD_LABELS[$key] ?? str_replace('_', ' ', $key),
                    'old' => $oldValues->has($key) ? $this->displayValue($key, $oldValue, $log) : null,
                    'new' => $newValues->has($key) ? $this->displayValue($key, $newValue, $log) : null,
                ];
            })
            ->filter()
            ->values();
    }

    private function displayValue(string $key, mixed $value, AdminActivityLog $log): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (str_ends_with($key, '_cents')) {
            return number_format(((int) $value) / 100, 2, '.', ' ').' грн';
        }

        if ($key === 'status' && str_starts_with($log->event, 'order.')) {
            return self::ORDER_STATUS_LABELS[(string) $value] ?? (string) $value;
        }

        if ($key === 'status' && str_starts_with($log->event, 'product.')) {
            return self::PRODUCT_STATUS_LABELS[(string) $value] ?? (string) $value;
        }

        if ($key === 'stock_status') {
            return self::STOCK_STATUS_LABELS[(string) $value] ?? (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'Так' : 'Ні';
        }

        if ($key === 'permissions' && is_array($value)) {
            return count($value).' доступів';
        }

        if ($key === 'changed_fields' && is_array($value)) {
            return implode(', ', $value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        if (str_ends_with($key, '_at')) {
            try {
                return CarbonImmutable::parse((string) $value)->format('d.m.Y H:i');
            } catch (Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    private function subjectUrl(AdminActivityLog $log): ?string
    {
        if (str_ends_with($log->event, '.deleted') || ! $log->subject_id) {
            return null;
        }

        return match ($log->subject_type) {
            Category::class => route('admin.categories.edit', $log->subject_id),
            Order::class => route('admin.orders.show', $log->subject_id),
            Product::class => route('admin.products.edit', $log->subject_id),
            ProductAttribute::class => route('admin.attributes.edit', $log->subject_id),
            ProductColorGroup::class => route('admin.color-groups.edit', $log->subject_id),
            Review::class => route('admin.reviews.edit', $log->subject_id),
            SizeChart::class => route('admin.size-charts.edit', $log->subject_id),
            default => null,
        };
    }

    private function eventGroup(string $event): string
    {
        if ($event === 'admin.module_viewed') {
            return 'modules';
        }

        if ($event === 'admin.login') {
            return 'logins';
        }

        if (str_starts_with($event, 'order.')) {
            return 'orders';
        }

        if (str_starts_with($event, 'product.')) {
            return 'products';
        }

        if (str_starts_with($event, 'site_structure.')) {
            return 'site_structure';
        }

        if (str_starts_with($event, 'catalog.')) {
            return 'catalog';
        }

        if (str_starts_with($event, 'payment_delivery.')) {
            return 'payment_delivery';
        }

        if (str_starts_with($event, 'seo.')) {
            return 'seo';
        }

        if (str_starts_with($event, 'settings.')) {
            return 'settings';
        }

        if (str_starts_with($event, 'roles.')) {
            return 'roles';
        }

        if (str_starts_with($event, 'system.')) {
            return 'system';
        }

        return 'logins';
    }

    private function eventClass(string $event): string
    {
        if ($event === 'admin.module_viewed') {
            return 'bg-slate-100 text-slate-700 ring-slate-200';
        }

        if (str_starts_with($event, 'order.')) {
            return 'bg-sky-50 text-sky-700 ring-sky-100';
        }

        if (str_starts_with($event, 'product.')) {
            return 'bg-violet-50 text-violet-700 ring-violet-100';
        }

        if (str_starts_with($event, 'site_structure.')) {
            return 'bg-amber-50 text-amber-700 ring-amber-100';
        }

        if (str_starts_with($event, 'catalog.')) {
            return 'bg-indigo-50 text-indigo-700 ring-indigo-100';
        }

        if (str_starts_with($event, 'payment_delivery.')) {
            return 'bg-cyan-50 text-cyan-700 ring-cyan-100';
        }

        if (str_starts_with($event, 'seo.')) {
            return 'bg-lime-50 text-lime-800 ring-lime-100';
        }

        if (str_starts_with($event, 'settings.')) {
            return 'bg-orange-50 text-orange-700 ring-orange-100';
        }

        if (str_starts_with($event, 'roles.')) {
            return 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-100';
        }

        if (str_starts_with($event, 'system.')) {
            return 'bg-rose-50 text-rose-700 ring-rose-100';
        }

        return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
    }

    private function roleLabel(?string $role): string
    {
        return match ($role) {
            'admin' => 'Адмін',
            'manager' => 'Менеджер',
            default => 'Працівник',
        };
    }
}
