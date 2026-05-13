<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\FilterSeoPage;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\SeoIndexingRule;
use App\Models\SeoRedirect;
use App\Models\SeoSetting;
use App\Models\SeoTemplate;
use App\Models\SitemapRun;
use App\Services\AdminActivityLogger;
use App\Services\Seo\SeoAuditService;
use App\Services\Seo\SeoResolver;
use App\Services\Seo\SeoTemplateRenderer;
use App\Services\Seo\SitemapGenerator;
use App\Support\AdminPermissions;
use App\Support\Catalog\CatalogSlug;
use App\Support\Catalog\FilterUrlBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SeoController extends Controller
{
    private const SECTIONS = [
        'overview' => 'Overview / Audit',
        'meta' => 'Meta & Templates',
        'schema' => 'Schema',
        'redirects' => 'Redirects',
        'indexing' => 'Indexing / Robots',
        'sitemap' => 'Sitemap',
        'filter-seo' => 'Filter SEO',
    ];

    private const ENTITY_LABELS = [
        'product' => 'Товар',
        'category' => 'Категорія',
        'page' => 'Сторінка',
        'filter' => 'Фільтр',
    ];

    private const FIELD_LABELS = [
        'title' => 'Title',
        'meta_description' => 'Meta description',
        'canonical_url' => 'Canonical',
    ];

    public function __construct(
        private readonly SeoAuditService $audit,
        private readonly SeoResolver $resolver,
        private readonly SitemapGenerator $sitemapGenerator,
        private readonly FilterUrlBuilder $filterUrlBuilder,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.seo.show', $this->firstAllowedSection($request));
    }

    public function show(Request $request, string $section = 'overview'): Response
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404);
        abort_unless($request->user()?->can($this->sectionPermission($section)), 403);

        return Inertia::render('Admin/Seo/Index', [
            'section' => $section,
            'tabs' => $this->tabs($request),
            'audit' => $request->user()?->can(AdminPermissions::SEO_AUDIT_VIEW) ? $this->audit->payload() : (object) [],
            'metaSettings' => $request->user()?->can(AdminPermissions::SEO_META_MANAGE) ? $this->metaSettings() : (object) [],
            'templates' => $request->user()?->can(AdminPermissions::SEO_META_MANAGE) ? $this->templates() : [],
            'schemaSettings' => $request->user()?->can(AdminPermissions::SEO_SCHEMA_MANAGE) ? $this->schemaSettings() : (object) [],
            'redirects' => $request->user()?->can(AdminPermissions::SEO_REDIRECTS_MANAGE)
                ? SeoRedirect::query()
                    ->latest('id')
                    ->paginate(20)
                    ->through(fn (SeoRedirect $redirect): array => $this->serializeRedirect($redirect))
                : $this->emptyPaginator(),
            'indexingSettings' => $request->user()?->can(AdminPermissions::SEO_INDEXING_MANAGE) ? $this->indexingSettings() : (object) [],
            'indexingRules' => $request->user()?->can(AdminPermissions::SEO_INDEXING_MANAGE)
                ? SeoIndexingRule::query()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (SeoIndexingRule $rule): array => $this->serializeIndexingRule($rule))
                    ->values()
                : [],
            'sitemap' => $request->user()?->can(AdminPermissions::SEO_SITEMAP_MANAGE) ? $this->sitemapPayload() : (object) [],
            'filterPages' => $request->user()?->can(AdminPermissions::SEO_FILTER_SEO_MANAGE)
                ? FilterSeoPage::query()
                    ->with('category:id,name,slug')
                    ->latest('id')
                    ->paginate(20)
                    ->through(fn (FilterSeoPage $page): array => $this->serializeFilterPage($page))
                : $this->emptyPaginator(),
            'categoryOptions' => $request->user()?->can(AdminPermissions::SEO_FILTER_SEO_MANAGE) ? $this->categoryOptions() : [],
            'filterAttributeOptions' => $request->user()?->can(AdminPermissions::SEO_FILTER_SEO_MANAGE) ? $this->filterAttributeOptions() : [],
            'options' => [
                'entities' => collect(self::ENTITY_LABELS)->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
                'fields' => collect(self::FIELD_LABELS)->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])->values(),
                'patternTypes' => [
                    ['value' => SeoIndexingRule::PATTERN_PREFIX, 'label' => 'Префікс URL'],
                    ['value' => SeoIndexingRule::PATTERN_EXACT, 'label' => 'Точний URL'],
                    ['value' => SeoIndexingRule::PATTERN_REGEX, 'label' => 'Regex'],
                ],
                'robotsDirectives' => [
                    ['value' => '', 'label' => 'Не додавати в robots.txt'],
                    ['value' => 'disallow', 'label' => 'Disallow'],
                    ['value' => 'allow', 'label' => 'Allow'],
                ],
                'statusCodes' => [
                    ['value' => 301, 'label' => '301 Permanent'],
                    ['value' => 302, 'label' => '302 Temporary'],
                    ['value' => 308, 'label' => '308 Permanent'],
                ],
            ],
        ]);
    }

    public function updateMeta(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings.default_title' => ['nullable', 'string', 'max:255'],
            'settings.default_meta_description' => ['nullable', 'string', 'max:320'],
            'settings.default_favicon_url' => ['nullable', 'string', 'max:255'],
            'settings.default_og_image_url' => ['nullable', 'string', 'max:255'],
            'settings.default_canonical_url' => ['nullable', 'string', 'max:255'],
            'templates' => ['required', 'array'],
            'templates.*.entity_type' => ['required', Rule::in(array_keys(self::ENTITY_LABELS))],
            'templates.*.field' => ['required', Rule::in(array_keys(self::FIELD_LABELS))],
            'templates.*.template' => ['required', 'string', 'max:1000'],
            'templates.*.is_active' => ['nullable', 'boolean'],
        ]);

        SeoSetting::putValue('meta', 'global', $this->cleanArray($data['settings'] ?? []));

        foreach ($data['templates'] as $index => $template) {
            SeoTemplate::query()->updateOrCreate(
                [
                    'entity_type' => $template['entity_type'],
                    'field' => $template['field'],
                ],
                [
                    'template' => trim($template['template']),
                    'is_active' => (bool) ($template['is_active'] ?? false),
                    'sort_order' => $index,
                ],
            );
        }

        return redirect()
            ->route('admin.seo.show', 'meta')
            ->with('success', 'SEO шаблони оновлено');
    }

    public function updateSchema(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_type' => ['required', Rule::in(['Organization', 'LocalBusiness'])],
            'name' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'social_links' => ['nullable', 'string', 'max:2000'],
            'enable_website_schema' => ['nullable', 'boolean'],
            'enable_search_action' => ['nullable', 'boolean'],
            'enable_product_schema' => ['nullable', 'boolean'],
            'enable_breadcrumbs' => ['nullable', 'boolean'],
            'enable_faq_schema' => ['nullable', 'boolean'],
        ]);

        SeoSetting::putValue('schema', 'global', [
            ...$this->cleanArray($data),
            'social_links' => $this->lines($data['social_links'] ?? ''),
            'enable_website_schema' => (bool) ($data['enable_website_schema'] ?? false),
            'enable_search_action' => (bool) ($data['enable_search_action'] ?? false),
            'enable_product_schema' => (bool) ($data['enable_product_schema'] ?? false),
            'enable_breadcrumbs' => (bool) ($data['enable_breadcrumbs'] ?? false),
            'enable_faq_schema' => (bool) ($data['enable_faq_schema'] ?? false),
        ]);

        return redirect()
            ->route('admin.seo.show', 'schema')
            ->with('success', 'Schema налаштування оновлено');
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        $data = $this->validatedRedirect($request);

        SeoRedirect::query()->create($data);

        return redirect()
            ->route('admin.seo.show', 'redirects')
            ->with('success', 'Редірект створено');
    }

    public function updateRedirect(Request $request, SeoRedirect $redirect): RedirectResponse
    {
        $data = $this->validatedRedirect($request, $redirect);

        $redirect->update($data);

        return redirect()
            ->route('admin.seo.show', 'redirects')
            ->with('success', 'Редірект оновлено');
    }

    public function destroyRedirect(SeoRedirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return redirect()
            ->route('admin.seo.show', 'redirects')
            ->with('success', 'Редірект видалено');
    }

    public function updateIndexing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'robots_txt' => ['nullable', 'string', 'max:20000'],
            'default_filter_policy' => ['required', Rule::in(['noindex', 'canonical', 'index_selected'])],
            'technical_paths' => ['nullable', 'string', 'max:4000'],
        ]);

        SeoSetting::putValue('indexing', 'global', [
            'robots_txt' => $data['robots_txt'] ?? '',
            'default_filter_policy' => $data['default_filter_policy'],
            'technical_paths' => $this->lines($data['technical_paths'] ?? ''),
        ]);

        return redirect()
            ->route('admin.seo.show', 'indexing')
            ->with('success', 'Правила індексації оновлено');
    }

    public function storeIndexingRule(Request $request): RedirectResponse
    {
        SeoIndexingRule::query()->create($this->validatedIndexingRule($request));

        return redirect()
            ->route('admin.seo.show', 'indexing')
            ->with('success', 'Правило індексації створено');
    }

    public function updateIndexingRule(Request $request, SeoIndexingRule $rule): RedirectResponse
    {
        $rule->update($this->validatedIndexingRule($request));

        return redirect()
            ->route('admin.seo.show', 'indexing')
            ->with('success', 'Правило індексації оновлено');
    }

    public function destroyIndexingRule(SeoIndexingRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()
            ->route('admin.seo.show', 'indexing')
            ->with('success', 'Правило індексації видалено');
    }

    public function regenerateSitemap(Request $request): RedirectResponse
    {
        $run = $this->sitemapGenerator->generate($request->user()?->id);

        app(AdminActivityLogger::class)->log(
            $request,
            'seo.sitemap_regenerated',
            $run,
            newValues: [
                'total_urls_count' => $run->total_urls_count,
                'status' => $run->status,
            ],
            description: 'Менеджер перегенерував sitemap',
        );

        return redirect()
            ->route('admin.seo.show', 'sitemap')
            ->with('success', "Sitemap перегенеровано: {$run->total_urls_count} URL");
    }

    public function storeFilterPage(Request $request): RedirectResponse
    {
        $data = $this->validatedFilterPage($request);

        FilterSeoPage::query()->create($data);

        return redirect()
            ->route('admin.seo.show', 'filter-seo')
            ->with('success', 'SEO сторінку фільтра створено');
    }

    public function updateFilterPage(Request $request, FilterSeoPage $page): RedirectResponse
    {
        $data = $this->validatedFilterPage($request, $page);

        $page->update($data);

        return redirect()
            ->route('admin.seo.show', 'filter-seo')
            ->with('success', 'SEO сторінку фільтра оновлено');
    }

    public function destroyFilterPage(FilterSeoPage $page): RedirectResponse
    {
        $page->delete();

        return redirect()
            ->route('admin.seo.show', 'filter-seo')
            ->with('success', 'SEO сторінку фільтра видалено');
    }

    private function validatedRedirect(Request $request, ?SeoRedirect $redirect = null): array
    {
        $request->merge([
            'source_path' => $this->normalizeSourcePath($request->input('source_path')),
            'target_url' => $this->normalizeTargetUrl($request->input('target_url')),
        ]);

        $data = $request->validate([
            'source_path' => ['required', 'string', 'max:255', Rule::unique('seo_redirects', 'source_path')->ignore($redirect?->id)],
            'target_url' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->validTargetUrl((string) $value)) {
                    $fail('Вкажи внутрішній шлях /new-url або повний https URL.');
                }
            }],
            'status_code' => ['required', 'integer', Rule::in([301, 302, 308])],
            'preserve_query' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ensureRedirectHasNoCycle($data['source_path'], $data['target_url'], $redirect?->id);

        return [
            ...$data,
            'preserve_query' => (bool) ($data['preserve_query'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];
    }

    private function validatedIndexingRule(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pattern' => ['required', 'string', 'max:255'],
            'pattern_type' => ['required', Rule::in([SeoIndexingRule::PATTERN_EXACT, SeoIndexingRule::PATTERN_PREFIX, SeoIndexingRule::PATTERN_REGEX])],
            'robots_directive' => ['nullable', Rule::in(['allow', 'disallow'])],
            'meta_robots' => ['nullable', 'string', 'max:120'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        return [
            ...$data,
            'robots_directive' => $this->nullableString($data['robots_directive'] ?? null),
            'meta_robots' => $this->nullableString($data['meta_robots'] ?? null),
            'canonical_url' => $this->nullableString($data['canonical_url'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function validatedFilterPage(Request $request, ?FilterSeoPage $page = null): array
    {
        $request->merge([
            'slug' => $this->resolveFilterSlug($request->input('slug'), $request->input('h1') ?: $request->input('title') ?: 'filter-page', $page?->id),
        ]);

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('filter_seo_pages', 'slug')->ignore($page?->id)],
            'filters' => ['required', 'array', 'min:1', 'max:8'],
            'filters.*.attribute_id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'filters.*.value_ids' => ['required', 'array', 'min:1', 'max:20'],
            'filters.*.value_ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],
            'h1' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
            'seo_text' => ['nullable', 'string'],
            'is_indexable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $filters = $this->normalizeFilterRows($data['filters'] ?? []);
        $this->ensureUniqueFilterPage($data['category_id'] ?? null, $filters, $page?->id);

        return [
            'category_id' => $data['category_id'] ?? null,
            'slug' => $data['slug'],
            'filters' => $filters,
            'h1' => $this->nullableString($data['h1'] ?? null),
            'title' => $this->nullableString($data['title'] ?? null),
            'meta_title' => $this->nullableString($data['meta_title'] ?? null),
            'meta_description' => $this->nullableString($data['meta_description'] ?? null),
            'canonical_url' => $this->nullableString($data['canonical_url'] ?? null),
            'seo_text' => $this->nullableString($data['seo_text'] ?? null),
            'is_indexable' => (bool) ($data['is_indexable'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function tabs(Request $request): array
    {
        return collect(self::SECTIONS)
            ->filter(fn (string $label, string $value): bool => (bool) $request->user()?->can($this->sectionPermission($value)))
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'route' => route('admin.seo.show', $value),
            ])
            ->values()
            ->all();
    }

    private function firstAllowedSection(Request $request): string
    {
        foreach (array_keys(self::SECTIONS) as $section) {
            if ($request->user()?->can($this->sectionPermission($section))) {
                return $section;
            }
        }

        abort(403);
    }

    private function sectionPermission(string $section): string
    {
        return match ($section) {
            'overview' => AdminPermissions::SEO_AUDIT_VIEW,
            'meta' => AdminPermissions::SEO_META_MANAGE,
            'schema' => AdminPermissions::SEO_SCHEMA_MANAGE,
            'redirects' => AdminPermissions::SEO_REDIRECTS_MANAGE,
            'indexing' => AdminPermissions::SEO_INDEXING_MANAGE,
            'sitemap' => AdminPermissions::SEO_SITEMAP_MANAGE,
            'filter-seo' => AdminPermissions::SEO_FILTER_SEO_MANAGE,
            default => AdminPermissions::SEO_AUDIT_VIEW,
        };
    }

    private function emptyPaginator(): array
    {
        return [
            'data' => [],
            'links' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 0,
            ],
        ];
    }

    private function metaSettings(): array
    {
        return array_replace([
            'default_title' => config('app.name', 'DomMood'),
            'default_meta_description' => '',
            'default_favicon_url' => '',
            'default_og_image_url' => '',
            'default_canonical_url' => url('/'),
        ], SeoSetting::getValue('meta', 'global'));
    }

    private function schemaSettings(): array
    {
        return array_replace([
            'organization_type' => 'Organization',
            'name' => config('app.name', 'DomMood'),
            'logo_url' => '',
            'phone' => '',
            'email' => '',
            'address' => '',
            'social_links' => [],
            'enable_website_schema' => true,
            'enable_search_action' => true,
            'enable_product_schema' => true,
            'enable_breadcrumbs' => true,
            'enable_faq_schema' => false,
        ], SeoSetting::getValue('schema', 'global'));
    }

    private function indexingSettings(): array
    {
        $settings = array_replace([
            'robots_txt' => "User-agent: *\nDisallow: /admin\nDisallow: /login\nDisallow: /register\n",
            'default_filter_policy' => 'noindex',
            'technical_paths' => ['/admin', '/login', '/register', '/cart', '/checkout'],
        ], SeoSetting::getValue('indexing', 'global'));

        return [
            ...$settings,
            'technical_paths_text' => implode("\n", $settings['technical_paths'] ?? []),
        ];
    }

    private function templates(): array
    {
        $stored = SeoTemplate::query()
            ->get()
            ->keyBy(fn (SeoTemplate $template): string => $template->entity_type.'.'.$template->field);

        return collect($this->resolver->defaultTemplates())
            ->flatMap(function (array $fields, string $entityType) use ($stored): array {
                return collect($fields)
                    ->map(function (string $defaultTemplate, string $field) use ($entityType, $stored): array {
                        $storedTemplate = $stored->get($entityType.'.'.$field);
                        $template = $storedTemplate?->template ?: $defaultTemplate;

                        return [
                            'entity_type' => $entityType,
                            'entity_label' => self::ENTITY_LABELS[$entityType] ?? $entityType,
                            'field' => $field,
                            'field_label' => self::FIELD_LABELS[$field] ?? $field,
                            'template' => $template,
                            'is_active' => $storedTemplate?->is_active ?? true,
                            'default_template' => $defaultTemplate,
                            'preview' => $this->templatePreview($entityType, $field, $template),
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    private function sitemapPayload(): array
    {
        $lastRun = SitemapRun::query()->latest('id')->first();

        return [
            'last_run' => $lastRun ? [
                'id' => $lastRun->id,
                'status' => $lastRun->status,
                'product_urls_count' => $lastRun->product_urls_count,
                'category_urls_count' => $lastRun->category_urls_count,
                'page_urls_count' => $lastRun->page_urls_count,
                'total_urls_count' => $lastRun->total_urls_count,
                'file_path' => $lastRun->file_path,
                'url' => $lastRun->meta['url'] ?? url('/sitemap.xml'),
                'finished_at' => $lastRun->finished_at?->format('d.m.Y H:i'),
            ] : null,
            'current_counts' => [
                'products' => Product::query()->where('status', 'active')->count(),
                'categories' => Category::query()->where('is_active', true)->count(),
                'pages' => ContentPage::query()->published()->count(),
                'filters' => FilterSeoPage::query()->where('is_indexable', true)->active()->count(),
            ],
        ];
    }

    private function templatePreview(string $entityType, string $field, string $template): string
    {
        $context = [
            'product_name' => 'Домашні капці Fluffy',
            'product_slug' => 'domashni-kaptsi-fluffy',
            'category_name' => 'Жіночі капці',
            'category_slug' => 'zhinochi-kaptsi',
            'page_title' => 'Доставка і оплата',
            'filter_h1' => 'Жіночі капці з хутром',
            'filter_slug' => 'zhinochi-kaptsi-z-hutrom',
            'price' => '1299.00',
            'product_url' => url('/catalog/zhinochi-kaptsi/domashni-kaptsi-fluffy'),
            'category_url' => url('/catalog/zhinochi-kaptsi'),
            'page_url' => url('/dostavka-i-oplata'),
            'filter_url' => url('/catalog/zhinochi-kaptsi/filter/material/shtuchne-hutro'),
        ];

        return app(SeoTemplateRenderer::class)->render($template, $context);
    }

    private function serializeRedirect(SeoRedirect $redirect): array
    {
        return [
            'id' => $redirect->id,
            'source_path' => $redirect->source_path,
            'target_url' => $redirect->target_url,
            'status_code' => $redirect->status_code,
            'preserve_query' => $redirect->preserve_query,
            'is_active' => $redirect->is_active,
            'hits' => $redirect->hits,
            'last_hit_at' => $redirect->last_hit_at?->format('d.m.Y H:i'),
            'notes' => $redirect->notes,
        ];
    }

    private function serializeIndexingRule(SeoIndexingRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'pattern' => $rule->pattern,
            'pattern_type' => $rule->pattern_type,
            'robots_directive' => $rule->robots_directive,
            'meta_robots' => $rule->meta_robots,
            'canonical_url' => $rule->canonical_url,
            'is_active' => $rule->is_active,
            'sort_order' => $rule->sort_order,
        ];
    }

    private function serializeFilterPage(FilterSeoPage $page): array
    {
        return [
            'id' => $page->id,
            'category_id' => $page->category_id,
            'category_name' => $page->category?->name,
            'slug' => $page->slug,
            'filters' => $page->filters ?? [],
            'filter_rows' => $this->filterRows($page->filters ?? []),
            'filters_label' => $this->filtersLabel($page->filters ?? []),
            'url' => $this->filterPageUrl($page),
            'h1' => $page->h1,
            'title' => $page->title,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'canonical_url' => $page->canonical_url,
            'seo_text' => $page->seo_text,
            'is_indexable' => $page->is_indexable,
            'is_active' => $page->is_active,
            'sort_order' => $page->sort_order,
            'preview' => $this->resolver->metaForFilterPage($page),
        ];
    }

    private function categoryOptions(): array
    {
        return Category::query()
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $category->name,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    private function filterAttributeOptions(): array
    {
        return ProductAttribute::query()
            ->filterable()
            ->with('values:id,attribute_id,value,slug,color_hex,sort_order')
            ->ordered()
            ->get()
            ->map(fn (ProductAttribute $attribute): array => [
                'id' => $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'type' => $attribute->type,
                'values' => $attribute->values
                    ->map(fn (AttributeValue $value): array => [
                        'id' => $value->id,
                        'value' => $value->value,
                        'slug' => $value->slug,
                        'color_hex' => $value->color_hex,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function normalizeSourcePath(mixed $path): string
    {
        $path = trim((string) $path);
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = '/'.ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function normalizeTargetUrl(mixed $target): string
    {
        $target = trim((string) $target);

        if ($target === '') {
            return '';
        }

        if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return $target;
        }

        return '/'.ltrim($target, '/');
    }

    private function validTargetUrl(string $target): bool
    {
        if (str_starts_with($target, '/')) {
            return true;
        }

        return filter_var($target, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($target, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function ensureRedirectHasNoCycle(string $source, string $target, ?int $ignoreId = null): void
    {
        $targetPath = $this->targetPath($target);

        if ($targetPath === $source) {
            throw ValidationException::withMessages([
                'target_url' => 'Редірект не може вести сам на себе.',
            ]);
        }

        $visited = [$source];

        for ($depth = 0; $depth < 10 && $targetPath; $depth++) {
            if (in_array($targetPath, $visited, true)) {
                throw ValidationException::withMessages([
                    'target_url' => 'Редірект створює цикл.',
                ]);
            }

            $visited[] = $targetPath;
            $next = SeoRedirect::query()
                ->where('source_path', $targetPath)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->value('target_url');

            $targetPath = $next ? $this->targetPath($next) : null;
        }
    }

    private function targetPath(string $target): ?string
    {
        if (! str_starts_with($target, 'http://') && ! str_starts_with($target, 'https://')) {
            return $this->normalizeSourcePath($target);
        }

        $host = parse_url($target, PHP_URL_HOST);
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($host && $appHost && $host !== $appHost) {
            return null;
        }

        return $this->normalizeSourcePath(parse_url($target, PHP_URL_PATH) ?: '/');
    }

    private function resolveFilterSlug(mixed $slug, mixed $fallback, ?int $ignoreId = null): string
    {
        $base = CatalogSlug::make($slug ?: $fallback) ?: 'filter-page';
        $candidate = $base;
        $counter = 2;

        while (FilterSeoPage::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function normalizeFilterRows(array $rows): array
    {
        $attributeIds = collect($rows)
            ->pluck('attribute_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $attributes = ProductAttribute::query()
            ->with('values:id,attribute_id,value,slug,sort_order')
            ->whereIn('id', $attributeIds)
            ->get()
            ->keyBy('id');

        $filters = [];

        foreach ($rows as $index => $row) {
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            $attribute = $attributes->get($attributeId);

            if (! $attribute || ! $attribute->is_filterable) {
                throw ValidationException::withMessages([
                    "filters.{$index}.attribute_id" => 'Обери активну характеристику, дозволену для фільтрів.',
                ]);
            }

            $valueIds = collect($row['value_ids'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $values = $attribute->values
                ->whereIn('id', $valueIds)
                ->sortBy('sort_order')
                ->values();

            if ($values->count() !== $valueIds->count()) {
                throw ValidationException::withMessages([
                    "filters.{$index}.value_ids" => 'У фільтрі є значення, яке не належить вибраній характеристиці.',
                ]);
            }

            $filters[$attribute->slug] = $values
                ->pluck('slug')
                ->filter()
                ->values()
                ->all();
        }

        return $this->filterUrlBuilder->normalizeFilters($filters);
    }

    private function ensureUniqueFilterPage(?int $categoryId, array $filters, ?int $ignoreId = null): void
    {
        $signature = $this->filterSignature($categoryId, $filters);

        $duplicate = FilterSeoPage::query()
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId), fn ($query) => $query->whereNull('category_id'))
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'filters'])
            ->first(fn (FilterSeoPage $page): bool => $this->filterSignature($categoryId, $page->filters ?? []) === $signature);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'filters' => 'SEO-сторінка для цієї комбінації фільтрів уже існує.',
            ]);
        }
    }

    private function filterSignature(?int $categoryId, array $filters): string
    {
        return ($categoryId ?: 'catalog').'|'.json_encode($this->filterUrlBuilder->normalizeFilters($filters));
    }

    private function filterRows(array $filters): array
    {
        $attributes = ProductAttribute::query()
            ->with('values:id,attribute_id,value,slug,sort_order')
            ->whereIn('slug', array_keys($filters))
            ->get()
            ->keyBy('slug');

        return collect($filters)
            ->map(function (array|string $valueSlugs, string $attributeSlug) use ($attributes): ?array {
                $attribute = $attributes->get($attributeSlug);

                if (! $attribute) {
                    return null;
                }

                $values = $attribute->values
                    ->whereIn('slug', (array) $valueSlugs)
                    ->values();

                return [
                    'attribute_id' => $attribute->id,
                    'attribute_slug' => $attribute->slug,
                    'attribute_name' => $attribute->name,
                    'value_ids' => $values->pluck('id')->values()->all(),
                    'value_slugs' => $values->pluck('slug')->values()->all(),
                    'value_names' => $values->pluck('value')->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function filtersLabel(array $filters): string
    {
        return collect($this->filterRows($filters))
            ->map(fn (array $row): string => $row['attribute_name'].': '.implode(', ', $row['value_names']))
            ->implode(' · ');
    }

    private function filterPageUrl(FilterSeoPage $page): string
    {
        $page->loadMissing('category:id,slug');
        $path = $this->filterUrlBuilder->build($page->category?->slug ?: 'catalog', $page->filters ?? []);

        return url($path);
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function cleanArray(array $value): array
    {
        return collect($value)
            ->map(fn (mixed $item): mixed => is_string($item) ? $this->nullableString($item) : $item)
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
