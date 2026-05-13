<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import SeoSnippetEditor from '@/components/Admin/SeoSnippetEditor.vue';
import Modal from '@/components/Modal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Code2,
    FileSearch,
    Globe2,
    Link2,
    ListChecks,
    Map,
    Pencil,
    RefreshCw,
    Route,
    Save,
    ShieldCheck,
    Tags,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    section: { type: String, required: true },
    tabs: { type: Array, required: true },
    audit: { type: Object, required: true },
    metaSettings: { type: Object, required: true },
    templates: { type: Array, required: true },
    schemaSettings: { type: Object, required: true },
    redirects: { type: Object, required: true },
    indexingSettings: { type: Object, required: true },
    indexingRules: { type: Array, required: true },
    sitemap: { type: Object, required: true },
    filterPages: { type: Object, required: true },
    categoryOptions: { type: Array, required: true },
    filterAttributeOptions: { type: Array, required: true },
    options: { type: Object, required: true },
});

const sectionMeta = {
    overview: {
        title: 'SEO Audit',
        eyebrow: 'SEO control center',
        icon: FileSearch,
    },
    meta: {
        title: 'Meta & Templates',
        eyebrow: 'Fallback-шаблони',
        icon: Tags,
    },
    schema: {
        title: 'Schema',
        eyebrow: 'Structured data',
        icon: Code2,
    },
    redirects: {
        title: 'Redirects',
        eyebrow: '301 / 302 / 308',
        icon: Route,
    },
    indexing: {
        title: 'Indexing / Robots',
        eyebrow: 'Robots, noindex, canonical',
        icon: ShieldCheck,
    },
    sitemap: {
        title: 'Sitemap',
        eyebrow: 'XML sitemap',
        icon: Map,
    },
    'filter-seo': {
        title: 'Filter SEO',
        eyebrow: 'SEO-сторінки фільтрів',
        icon: Globe2,
    },
};

const activeMeta = computed(() => sectionMeta[props.section] ?? sectionMeta.overview);
const redirectsList = computed(() => props.redirects.data ?? []);
const filterPagesList = computed(() => props.filterPages.data ?? []);
const inputClass = 'mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]';
const textareaClass = 'mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]';
const labelClass = 'text-xs font-extrabold uppercase tracking-wide text-slate-500';
const sampleTemplateContext = {
    product_name: 'Домашні капці Fluffy',
    product_slug: 'domashni-kaptsi-fluffy',
    category_name: 'Жіночі капці',
    category_slug: 'zhinochi-kaptsi',
    page_title: 'Доставка і оплата',
    filter_h1: 'Жіночі капці з хутром',
    filter_slug: 'zhinochi-kaptsi-z-hutrom',
    price: '1299.00',
    product_url: '/catalog/zhinochi-kaptsi/domashni-kaptsi-fluffy',
    category_url: '/catalog/zhinochi-kaptsi',
    page_url: '/dostavka-i-oplata',
    filter_url: '/catalog/zhinochi-kaptsi/filter/material/shtuchne-hutro',
};

const normalizeSeoText = (value) => String(value ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const renderTemplatePreview = (template) => normalizeSeoText(template)
    .replace(/\{([a-z0-9_]+)\}/gi, (_, key) => sampleTemplateContext[key] ?? '');

const priorityClass = (priority) => ({
    high: 'bg-red-50 text-red-700',
    medium: 'bg-amber-50 text-amber-700',
    low: 'bg-slate-100 text-slate-600',
}[priority] ?? 'bg-slate-100 text-slate-600');

const boolValue = (value) => (value ? 1 : 0);
const emptyFilterRow = () => ({
    attribute_id: '',
    value_ids: [],
});

const metaForm = useForm({
    settings: { ...props.metaSettings },
    templates: props.templates.map((template) => ({ ...template })),
});

const templateSnippetPreviews = computed(() => {
    const groups = {};

    metaForm.templates.forEach((template) => {
        groups[template.entity_type] ??= {
            entityType: template.entity_type,
            label: template.entity_label,
            title: '',
            description: '',
            url: '',
        };

        const preview = renderTemplatePreview(template.template);

        if (template.field === 'title') {
            groups[template.entity_type].title = preview;
        }

        if (template.field === 'meta_description') {
            groups[template.entity_type].description = preview;
        }

        if (template.field === 'canonical_url') {
            groups[template.entity_type].url = preview;
        }
    });

    return Object.values(groups);
});

const schemaForm = useForm({
    ...props.schemaSettings,
    social_links: (props.schemaSettings.social_links ?? []).join('\n'),
});

const indexingForm = useForm({
    robots_txt: props.indexingSettings.robots_txt ?? '',
    default_filter_policy: props.indexingSettings.default_filter_policy ?? 'noindex',
    technical_paths: props.indexingSettings.technical_paths_text ?? '',
});

const editingRedirectId = ref(null);
const redirectForm = useForm({
    source_path: '',
    target_url: '',
    status_code: 301,
    preserve_query: true,
    is_active: true,
    notes: '',
});

const editingRuleId = ref(null);
const ruleForm = useForm({
    name: '',
    pattern: '',
    pattern_type: 'prefix',
    robots_directive: '',
    meta_robots: '',
    canonical_url: '',
    is_active: true,
    sort_order: 0,
});

const editingFilterId = ref(null);
const isFilterModalOpen = ref(false);
const filterForm = useForm({
    category_id: '',
    slug: '',
    filters: [emptyFilterRow()],
    h1: '',
    title: '',
    meta_title: '',
    meta_description: '',
    canonical_url: '',
    seo_text: '',
    is_indexable: true,
    is_active: true,
    sort_order: 0,
});

const filterValuesFor = (attributeId) => {
    const attribute = props.filterAttributeOptions.find((item) => Number(item.id) === Number(attributeId));

    return attribute?.values ?? [];
};

const selectedFilterCategory = computed(() => props.categoryOptions
    .find((category) => Number(category.id) === Number(filterForm.category_id)) ?? null);

const filterUrlFallback = computed(() => {
    if (filterForm.canonical_url) {
        return filterForm.canonical_url;
    }

    const categorySlug = selectedFilterCategory.value?.slug || 'catalog';

    return filterForm.slug
        ? `/catalog/${categorySlug}/filter/${filterForm.slug}`
        : `/catalog/${categorySlug}/filter`;
});

const filterTitleFallback = computed(() => filterForm.h1 || filterForm.title || filterForm.slug || 'SEO сторінка фільтра');

const filterDescriptionFallback = computed(() => (
    `${filterForm.h1 || filterForm.title || 'Добірка товарів'}: товари DomMood з актуальними цінами та доставкою по Україні.`
));

const availableFilterAttributesFor = (row) => {
    const used = new Set(filterForm.filters
        .filter((item) => item !== row)
        .map((item) => Number(item.attribute_id))
        .filter(Boolean));

    return props.filterAttributeOptions.filter((attribute) => !used.has(Number(attribute.id)));
};

const addFilterRow = () => {
    filterForm.filters.push(emptyFilterRow());
};

const removeFilterRow = (index) => {
    if (filterForm.filters.length === 1) {
        filterForm.filters = [emptyFilterRow()];
        return;
    }

    filterForm.filters.splice(index, 1);
};

const onFilterAttributeChanged = (row) => {
    row.value_ids = [];
};

const submitMeta = () => {
    metaForm
        .transform((data) => ({
            settings: data.settings,
            templates: data.templates.map((template) => ({
                entity_type: template.entity_type,
                field: template.field,
                template: template.template,
                is_active: boolValue(template.is_active),
            })),
        }))
        .put(route('admin.seo.meta.update'), { preserveScroll: true });
};

const submitSchema = () => {
    schemaForm
        .transform((data) => ({
            ...data,
            enable_website_schema: boolValue(data.enable_website_schema),
            enable_search_action: boolValue(data.enable_search_action),
            enable_product_schema: boolValue(data.enable_product_schema),
            enable_breadcrumbs: boolValue(data.enable_breadcrumbs),
            enable_faq_schema: boolValue(data.enable_faq_schema),
        }))
        .put(route('admin.seo.schema.update'), { preserveScroll: true });
};

const resetRedirect = () => {
    editingRedirectId.value = null;
    redirectForm.defaults({
        source_path: '',
        target_url: '',
        status_code: 301,
        preserve_query: true,
        is_active: true,
        notes: '',
    });
    redirectForm.reset();
    redirectForm.clearErrors();
};

const editRedirect = (redirect) => {
    editingRedirectId.value = redirect.id;
    redirectForm.defaults({
        source_path: redirect.source_path,
        target_url: redirect.target_url,
        status_code: redirect.status_code,
        preserve_query: redirect.preserve_query,
        is_active: redirect.is_active,
        notes: redirect.notes ?? '',
    });
    redirectForm.reset();
    redirectForm.clearErrors();
};

const submitRedirect = () => {
    const options = { preserveScroll: true, onSuccess: resetRedirect };
    const payload = (data) => ({
        ...data,
        preserve_query: boolValue(data.preserve_query),
        is_active: boolValue(data.is_active),
    });

    if (editingRedirectId.value) {
        redirectForm.transform(payload).put(route('admin.seo.redirects.update', editingRedirectId.value), options);
        return;
    }

    redirectForm.transform(payload).post(route('admin.seo.redirects.store'), options);
};

const submitIndexing = () => {
    indexingForm.put(route('admin.seo.indexing.update'), { preserveScroll: true });
};

const resetRule = () => {
    editingRuleId.value = null;
    ruleForm.defaults({
        name: '',
        pattern: '',
        pattern_type: 'prefix',
        robots_directive: '',
        meta_robots: '',
        canonical_url: '',
        is_active: true,
        sort_order: 0,
    });
    ruleForm.reset();
    ruleForm.clearErrors();
};

const editRule = (rule) => {
    editingRuleId.value = rule.id;
    ruleForm.defaults({
        name: rule.name,
        pattern: rule.pattern,
        pattern_type: rule.pattern_type,
        robots_directive: rule.robots_directive ?? '',
        meta_robots: rule.meta_robots ?? '',
        canonical_url: rule.canonical_url ?? '',
        is_active: rule.is_active,
        sort_order: rule.sort_order,
    });
    ruleForm.reset();
    ruleForm.clearErrors();
};

const submitRule = () => {
    const options = { preserveScroll: true, onSuccess: resetRule };
    const payload = (data) => ({
        ...data,
        robots_directive: data.robots_directive || null,
        is_active: boolValue(data.is_active),
    });

    if (editingRuleId.value) {
        ruleForm.transform(payload).put(route('admin.seo.indexing-rules.update', editingRuleId.value), options);
        return;
    }

    ruleForm.transform(payload).post(route('admin.seo.indexing-rules.store'), options);
};

const resetFilter = () => {
    isFilterModalOpen.value = false;
    editingFilterId.value = null;
    filterForm.defaults({
        category_id: '',
        slug: '',
        filters: [emptyFilterRow()],
        h1: '',
        title: '',
        meta_title: '',
        meta_description: '',
        canonical_url: '',
        seo_text: '',
        is_indexable: true,
        is_active: true,
        sort_order: 0,
    });
    filterForm.reset();
    filterForm.clearErrors();
};

const openCreateFilter = () => {
    resetFilter();
    isFilterModalOpen.value = true;
};

const editFilter = (page) => {
    editingFilterId.value = page.id;
    isFilterModalOpen.value = true;
    const rows = page.filter_rows?.length
        ? page.filter_rows.map((row) => ({
            attribute_id: row.attribute_id,
            value_ids: row.value_ids ?? [],
        }))
        : [emptyFilterRow()];

    filterForm.defaults({
        category_id: page.category_id ?? '',
        slug: page.slug,
        filters: rows,
        h1: page.h1 ?? '',
        title: page.title ?? '',
        meta_title: page.meta_title ?? '',
        meta_description: page.meta_description ?? '',
        canonical_url: page.canonical_url ?? '',
        seo_text: page.seo_text ?? '',
        is_indexable: page.is_indexable,
        is_active: page.is_active,
        sort_order: page.sort_order,
    });
    filterForm.reset();
    filterForm.clearErrors();
};

const closeFilterModal = () => {
    if (!filterForm.processing) {
        resetFilter();
    }
};

const submitFilter = () => {
    const options = { preserveScroll: true, onSuccess: resetFilter };
    const payload = (data) => ({
        ...data,
        category_id: data.category_id || null,
        filters: data.filters
            .map((row) => ({
                attribute_id: row.attribute_id || null,
                value_ids: row.value_ids ?? [],
            }))
            .filter((row) => row.attribute_id && row.value_ids.length),
        is_indexable: boolValue(data.is_indexable),
        is_active: boolValue(data.is_active),
    });

    if (editingFilterId.value) {
        filterForm.transform(payload).put(route('admin.seo.filter-pages.update', editingFilterId.value), options);
        return;
    }

    filterForm.transform(payload).post(route('admin.seo.filter-pages.store'), options);
};

const destroyItem = (url, message) => {
    if (!window.confirm(message)) {
        return;
    }

    router.delete(url, { preserveScroll: true });
};

const regenerateSitemap = () => {
    router.post(route('admin.seo.sitemap.regenerate'), {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="activeMeta.title" />

    <AuthenticatedLayout>
        <div class="space-y-5">
            <section class="rounded-lg bg-white px-5 py-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)] lg:px-7">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-4">
                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20">
                            <component :is="activeMeta.icon" class="h-7 w-7" />
                        </span>
                        <div>
                            <p class="text-sm font-extrabold text-slate-500">{{ activeMeta.eyebrow }}</p>
                            <h1 class="mt-1 text-3xl font-black tracking-tight text-[#343241]">{{ activeMeta.title }}</h1>
                        </div>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm font-extrabold text-[#343241]">
                        SEO score: {{ audit.summary.score }} / 100
                    </div>
                </div>
            </section>

            <nav class="flex flex-wrap gap-2 rounded-lg bg-white p-2 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <Link
                    v-for="tab in tabs"
                    :key="tab.value"
                    :href="tab.route"
                    class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-bold transition"
                    :class="section === tab.value ? 'bg-[#7561f7] text-white shadow-[0_10px_24px_rgba(117,97,247,0.22)]' : 'text-slate-600 hover:bg-[#f5f4ff] hover:text-[#7561f7]'"
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <section v-if="section === 'overview'" class="space-y-5">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="text-sm font-extrabold text-slate-500">Проблеми</div>
                        <div class="mt-2 text-3xl font-black text-[#343241]">{{ audit.summary.issues_total }}</div>
                    </article>
                    <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="text-sm font-extrabold text-slate-500">Активні шаблони</div>
                        <div class="mt-2 text-3xl font-black text-[#343241]">{{ audit.summary.active_templates }}</div>
                    </article>
                    <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="text-sm font-extrabold text-slate-500">Редіректи</div>
                        <div class="mt-2 text-3xl font-black text-[#343241]">{{ audit.summary.active_redirects }}</div>
                    </article>
                    <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="text-sm font-extrabold text-slate-500">Filter SEO</div>
                        <div class="mt-2 text-3xl font-black text-[#343241]">{{ audit.summary.indexable_filter_pages }}</div>
                    </article>
                </div>

                <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-lg font-black text-[#343241]">SEO аудит</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="issue in audit.issues" :key="issue.key" class="flex items-center justify-between gap-4 px-5 py-4">
                            <div>
                                <div class="text-sm font-black text-[#343241]">{{ issue.label }}</div>
                                <div class="mt-1 text-xs font-bold text-slate-500">{{ issue.key }}</div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="rounded-full px-3 py-1 text-xs font-black" :class="priorityClass(issue.priority)">
                                    {{ issue.priority }}
                                </span>
                                <span class="min-w-10 text-right text-lg font-black text-[#343241]">{{ issue.count }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <form v-if="section === 'meta'" class="space-y-5" @submit.prevent="submitMeta">
                <SeoSnippetEditor
                    v-model:title="metaForm.settings.default_title"
                    v-model:description="metaForm.settings.default_meta_description"
                    v-model:canonical-url="metaForm.settings.default_canonical_url"
                    :show-seo-text="false"
                    title-fallback="DomMood - товари для дому та щоденного затишку"
                    description-fallback="DomMood: категорії, новинки, актуальні ціни та наявність для швидкої покупки онлайн."
                    url-fallback="/"
                    field-id-prefix="default_meta"
                    title-placeholder="Default title"
                    description-placeholder="Default meta description"
                    canonical-placeholder="https://dommood.com.ua/"
                    intro="Глобальний fallback використовується, коли конкретна сторінка або шаблон не має власного meta."
                    :errors="{
                        title: metaForm.errors['settings.default_title'],
                        description: metaForm.errors['settings.default_meta_description'],
                        canonical_url: metaForm.errors['settings.default_canonical_url'],
                    }"
                />

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-black text-[#343241]">Базові ресурси</h2>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <label :class="labelClass">Favicon URL</label>
                            <input v-model="metaForm.settings.default_favicon_url" :class="inputClass" type="text" />
                        </div>
                        <div>
                            <label :class="labelClass">Default OG image URL</label>
                            <input v-model="metaForm.settings.default_og_image_url" :class="inputClass" type="text" />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-lg font-black text-[#343241]">Шаблони fallback</h2>
                    </div>
                    <div class="grid gap-3 border-b border-slate-100 p-5 xl:grid-cols-4">
                        <article v-for="snippet in templateSnippetPreviews" :key="snippet.entityType" class="rounded-xl border border-slate-100 bg-[#fbfcff] p-4">
                            <div class="text-xs font-black uppercase tracking-wide text-slate-400">{{ snippet.label }}</div>
                            <div class="mt-3 break-words text-base font-medium leading-5 text-[#1a0dab]">{{ snippet.title }}</div>
                            <div class="mt-1 break-words text-xs font-bold leading-5 text-[#12823b]">{{ snippet.url }}</div>
                            <p class="mt-2 max-h-16 overflow-hidden text-xs font-semibold leading-5 text-slate-600">{{ snippet.description }}</p>
                        </article>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <article v-for="(template, index) in metaForm.templates" :key="`${template.entity_type}-${template.field}`" class="grid gap-4 px-5 py-4 xl:grid-cols-[180px_1fr_1fr_120px] xl:items-start">
                            <div>
                                <div class="text-sm font-black text-[#343241]">{{ template.entity_label }}</div>
                                <div class="mt-1 text-xs font-bold text-slate-500">{{ template.field_label }}</div>
                            </div>
                            <div>
                                <label :class="labelClass">Template</label>
                                <textarea v-model="metaForm.templates[index].template" :class="textareaClass" rows="2"></textarea>
                                <InputError class="mt-1" :message="metaForm.errors[`templates.${index}.template`]" />
                            </div>
                            <div>
                                <label :class="labelClass">Preview</label>
                                <div class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">
                                    {{ renderTemplatePreview(metaForm.templates[index].template) }}
                                </div>
                            </div>
                            <label class="mt-7 inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                                <input v-model="metaForm.templates[index].is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                Активний
                            </label>
                        </article>
                    </div>
                    <div class="border-t border-slate-100 px-5 py-4">
                        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-lg bg-[#343241] px-5 text-sm font-extrabold text-white hover:bg-[#24212f]" :disabled="metaForm.processing">
                            <Save class="h-4 w-4" />
                            Зберегти Meta
                        </button>
                    </div>
                </section>
            </form>

            <form v-if="section === 'schema'" class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submitSchema">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label :class="labelClass">Тип організації</label>
                        <select v-model="schemaForm.organization_type" :class="inputClass">
                            <option value="Organization">Organization</option>
                            <option value="LocalBusiness">LocalBusiness</option>
                        </select>
                    </div>
                    <div>
                        <label :class="labelClass">Назва</label>
                        <input v-model="schemaForm.name" :class="inputClass" type="text" />
                    </div>
                    <div>
                        <label :class="labelClass">Logo URL</label>
                        <input v-model="schemaForm.logo_url" :class="inputClass" type="text" />
                    </div>
                    <div>
                        <label :class="labelClass">Телефон</label>
                        <input v-model="schemaForm.phone" :class="inputClass" type="text" />
                    </div>
                    <div>
                        <label :class="labelClass">Email</label>
                        <input v-model="schemaForm.email" :class="inputClass" type="email" />
                    </div>
                    <div>
                        <label :class="labelClass">Адреса</label>
                        <input v-model="schemaForm.address" :class="inputClass" type="text" />
                    </div>
                    <div class="lg:col-span-2">
                        <label :class="labelClass">Соцмережі</label>
                        <textarea v-model="schemaForm.social_links" :class="textareaClass" rows="4"></textarea>
                    </div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label v-for="field in ['enable_website_schema', 'enable_search_action', 'enable_product_schema', 'enable_breadcrumbs', 'enable_faq_schema']" :key="field" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600">
                        <input v-model="schemaForm[field]" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                        {{ field.replace('enable_', '').replace('_', ' ') }}
                    </label>
                </div>
                <button type="submit" class="mt-5 inline-flex h-11 items-center gap-2 rounded-lg bg-[#343241] px-5 text-sm font-extrabold text-white hover:bg-[#24212f]" :disabled="schemaForm.processing">
                    <Save class="h-4 w-4" />
                    Зберегти Schema
                </button>
            </form>

            <section v-if="section === 'redirects'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-lg font-black text-[#343241]">Редіректи</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="redirect in redirectsList" :key="redirect.id" class="grid gap-3 px-5 py-4 lg:grid-cols-[1fr_1fr_110px] lg:items-center">
                            <div>
                                <div class="font-black text-[#343241]">{{ redirect.source_path }}</div>
                                <div class="mt-1 text-xs font-bold text-slate-500">{{ redirect.status_code }} · hits {{ redirect.hits }}</div>
                            </div>
                            <div class="break-all text-sm font-semibold text-slate-600">{{ redirect.target_url }}</div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="editRedirect(redirect)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50" @click="destroyItem(route('admin.seo.redirects.destroy', redirect.id), `Видалити редірект ${redirect.source_path}?`)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div v-if="redirectsList.length === 0" class="px-5 py-10 text-center text-sm font-bold text-slate-500">Редіректів ще немає.</div>
                    </div>
                </div>

                <form class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submitRedirect">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-black text-[#343241]">{{ editingRedirectId ? 'Редагувати' : 'Новий редірект' }}</h2>
                        <button v-if="editingRedirectId" type="button" class="text-slate-400 hover:text-slate-700" @click="resetRedirect">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div>
                            <label :class="labelClass">Source path</label>
                            <input v-model="redirectForm.source_path" :class="inputClass" type="text" placeholder="/old-url" />
                            <InputError class="mt-1" :message="redirectForm.errors.source_path" />
                        </div>
                        <div>
                            <label :class="labelClass">Target URL</label>
                            <input v-model="redirectForm.target_url" :class="inputClass" type="text" placeholder="/new-url" />
                            <InputError class="mt-1" :message="redirectForm.errors.target_url" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">Status</label>
                                <select v-model="redirectForm.status_code" :class="inputClass">
                                    <option v-for="status in options.statusCodes" :key="status.value" :value="status.value">{{ status.label }}</option>
                                </select>
                            </div>
                            <label class="mt-7 inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                                <input v-model="redirectForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                Активний
                            </label>
                        </div>
                        <textarea v-model="redirectForm.notes" :class="textareaClass" rows="3" placeholder="Нотатка"></textarea>
                    </div>
                    <button type="submit" class="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#343241] text-sm font-extrabold text-white hover:bg-[#24212f]" :disabled="redirectForm.processing">
                        <Save class="h-4 w-4" />
                        Зберегти
                    </button>
                </form>
            </section>

            <section v-if="section === 'indexing'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
                <form class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submitIndexing">
                    <div class="grid gap-4">
                        <div>
                            <label :class="labelClass">Robots.txt</label>
                            <textarea v-model="indexingForm.robots_txt" :class="textareaClass" rows="10"></textarea>
                        </div>
                        <div>
                            <label :class="labelClass">Політика фільтрів</label>
                            <select v-model="indexingForm.default_filter_policy" :class="inputClass">
                                <option value="noindex">Noindex за замовчуванням</option>
                                <option value="canonical">Canonical на категорію</option>
                                <option value="index_selected">Індексувати тільки вибрані Filter SEO</option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelClass">Технічні URL</label>
                            <textarea v-model="indexingForm.technical_paths" :class="textareaClass" rows="5"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="mt-5 inline-flex h-11 items-center gap-2 rounded-lg bg-[#343241] px-5 text-sm font-extrabold text-white hover:bg-[#24212f]" :disabled="indexingForm.processing">
                        <Save class="h-4 w-4" />
                        Зберегти Indexing
                    </button>
                </form>

                <div class="space-y-5">
                    <form class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submitRule">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-black text-[#343241]">{{ editingRuleId ? 'Редагувати правило' : 'Нове правило' }}</h2>
                            <button v-if="editingRuleId" type="button" class="text-slate-400 hover:text-slate-700" @click="resetRule">
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                        <div class="mt-4 space-y-3">
                            <input v-model="ruleForm.name" :class="inputClass" type="text" placeholder="Назва правила" />
                            <input v-model="ruleForm.pattern" :class="inputClass" type="text" placeholder="/checkout" />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <select v-model="ruleForm.pattern_type" :class="inputClass">
                                    <option v-for="type in options.patternTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                                <select v-model="ruleForm.robots_directive" :class="inputClass">
                                    <option v-for="directive in options.robotsDirectives" :key="directive.value" :value="directive.value">{{ directive.label }}</option>
                                </select>
                            </div>
                            <input v-model="ruleForm.meta_robots" :class="inputClass" type="text" placeholder="noindex,nofollow" />
                            <input v-model="ruleForm.canonical_url" :class="inputClass" type="text" placeholder="Canonical URL" />
                            <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                                <input v-model="ruleForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                Активне
                            </label>
                        </div>
                        <button type="submit" class="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#343241] text-sm font-extrabold text-white hover:bg-[#24212f]" :disabled="ruleForm.processing">
                            <Save class="h-4 w-4" />
                            Зберегти правило
                        </button>
                    </form>

                    <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="divide-y divide-slate-100">
                            <div v-for="rule in indexingRules" :key="rule.id" class="px-5 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-black text-[#343241]">{{ rule.name }}</div>
                                        <div class="mt-1 text-xs font-bold text-slate-500">{{ rule.pattern_type }} · {{ rule.pattern }}</div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="editRule(rule)">
                                            <Pencil class="h-4 w-4" />
                                        </button>
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50" @click="destroyItem(route('admin.seo.indexing-rules.destroy', rule.id), `Видалити правило ${rule.name}?`)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="indexingRules.length === 0" class="px-5 py-8 text-center text-sm font-bold text-slate-500">Правил ще немає.</div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="section === 'sitemap'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-black text-[#343241]">Поточні URL</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-4">
                            <div class="text-sm font-bold text-slate-500">Товари</div>
                            <div class="mt-1 text-2xl font-black text-[#343241]">{{ sitemap.current_counts.products }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <div class="text-sm font-bold text-slate-500">Категорії</div>
                            <div class="mt-1 text-2xl font-black text-[#343241]">{{ sitemap.current_counts.categories }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <div class="text-sm font-bold text-slate-500">Сторінки</div>
                            <div class="mt-1 text-2xl font-black text-[#343241]">{{ sitemap.current_counts.pages }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4">
                            <div class="text-sm font-bold text-slate-500">Filter SEO</div>
                            <div class="mt-1 text-2xl font-black text-[#343241]">{{ sitemap.current_counts.filters }}</div>
                        </div>
                    </div>
                </div>
                <aside class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-black text-[#343241]">Остання генерація</h2>
                    <div v-if="sitemap.last_run" class="mt-4 space-y-2 text-sm font-bold text-slate-600">
                        <div>URL: {{ sitemap.last_run.total_urls_count }}</div>
                        <div>Файл: {{ sitemap.last_run.file_path }}</div>
                        <div>Дата: {{ sitemap.last_run.finished_at }}</div>
                    </div>
                    <div v-else class="mt-4 text-sm font-bold text-slate-500">Sitemap ще не генерувався.</div>
                    <button type="button" class="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#343241] text-sm font-extrabold text-white hover:bg-[#24212f]" @click="regenerateSitemap">
                        <RefreshCw class="h-4 w-4" />
                        Перегенерувати sitemap
                    </button>
                </aside>
            </section>

            <section v-if="section === 'filter-seo'" class="space-y-5">
                <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-black text-[#343241]">SEO сторінки фільтрів</h2>
                        <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#343241] px-4 text-sm font-extrabold text-white hover:bg-[#24212f]" @click="openCreateFilter">
                            <Tags class="h-4 w-4" />
                            Нова Filter SEO
                        </button>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <article v-for="page in filterPagesList" :key="page.id" class="px-5 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-black text-[#343241]">{{ page.h1 || page.title || page.slug }}</div>
                                    <div class="mt-1 text-xs font-bold text-slate-500">{{ page.category_name || 'Без категорії' }} · {{ page.url }}</div>
                                    <div v-if="page.filters_label" class="mt-2 text-xs font-extrabold text-[#7561f7]">{{ page.filters_label }}</div>
                                    <div class="mt-2 text-sm font-semibold text-slate-600">{{ page.preview.title }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="editFilter(page)">
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50" @click="destroyItem(route('admin.seo.filter-pages.destroy', page.id), `Видалити Filter SEO ${page.slug}?`)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </article>
                        <div v-if="filterPagesList.length === 0" class="px-5 py-8 text-center text-sm font-bold text-slate-500">SEO сторінок фільтрів ще немає.</div>
                    </div>
                </div>
            </section>

            <Modal :show="isFilterModalOpen" max-width="2xl" :closeable="!filterForm.processing" @close="closeFilterModal">
                <form class="max-h-[calc(100vh-3rem)] overflow-y-auto p-5" @submit.prevent="submitFilter">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Filter SEO</p>
                            <h2 class="mt-1 text-xl font-black text-[#343241]">{{ editingFilterId ? 'Редагувати Filter SEO' : 'Нова Filter SEO' }}</h2>
                        </div>
                        <button type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:border-[#7561f7] hover:text-[#7561f7]" @click="closeFilterModal">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <label :class="labelClass">Категорія</label>
                            <select v-model="filterForm.category_id" :class="inputClass">
                                <option value="">Без категорії</option>
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">{{ category.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelClass">Внутрішній slug</label>
                            <input v-model="filterForm.slug" :class="inputClass" type="text" placeholder="zhinochi-kaptsi-z-hutrom" />
                            <InputError class="mt-1" :message="filterForm.errors.slug" />
                        </div>
                    </div>

                    <div class="mt-5 space-y-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-black text-[#343241]">Фільтри з каталогу</div>
                            <button type="button" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-extrabold text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="addFilterRow">
                                Додати
                            </button>
                        </div>
                        <div v-for="(row, index) in filterForm.filters" :key="`filter-row-${index}`" class="rounded-lg border border-slate-200 bg-white p-3">
                            <div class="flex items-start gap-2">
                                <div class="grid flex-1 gap-3">
                                    <select v-model="row.attribute_id" :class="inputClass" @change="onFilterAttributeChanged(row)">
                                        <option value="">Характеристика</option>
                                        <option v-for="attribute in availableFilterAttributesFor(row)" :key="attribute.id" :value="attribute.id">{{ attribute.name }}</option>
                                    </select>
                                    <select v-model="row.value_ids" :class="textareaClass" multiple size="4">
                                        <option v-for="value in filterValuesFor(row.attribute_id)" :key="value.id" :value="value.id">{{ value.value }}</option>
                                    </select>
                                </div>
                                <button type="button" class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50" @click="removeFilterRow(index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                            <InputError class="mt-2" :message="filterForm.errors[`filters.${index}.attribute_id`]" />
                            <InputError class="mt-2" :message="filterForm.errors[`filters.${index}.value_ids`]" />
                        </div>
                        <InputError class="mt-1" :message="filterForm.errors.filters" />
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <label :class="labelClass">H1</label>
                            <input v-model="filterForm.h1" :class="inputClass" type="text" placeholder="Жіночі капці з хутром" />
                        </div>
                        <div>
                            <label :class="labelClass">Внутрішній title</label>
                            <input v-model="filterForm.title" :class="inputClass" type="text" placeholder="Добірка для адмінки або H1 fallback" />
                        </div>
                    </div>

                    <div class="mt-5">
                        <SeoSnippetEditor
                            v-model:title="filterForm.meta_title"
                            v-model:description="filterForm.meta_description"
                            v-model:canonical-url="filterForm.canonical_url"
                            v-model:seo-text="filterForm.seo_text"
                            :title-fallback="filterTitleFallback"
                            :description-fallback="filterDescriptionFallback"
                            :url-fallback="filterUrlFallback"
                            field-id-prefix="filter_seo"
                            title-placeholder="Жіночі капці з хутром купити | DomMood"
                            description-placeholder="Meta description для SEO-сторінки фільтра"
                            canonical-placeholder="/catalog/zhinochi-kaptsi/filter/material/shtuchne-hutro"
                            seo-text-placeholder="SEO текст для нижнього блоку сторінки фільтра"
                            intro="Для filter SEO важливо тримати H1, title, description і canonical в одному змістовому напрямку."
                            compact
                            :errors="filterForm.errors"
                        />
                    </div>

                    <div class="mt-5 flex flex-col gap-4 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                                <input v-model="filterForm.is_indexable" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                Indexable
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                                <input v-model="filterForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                Активна
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 px-4 text-sm font-extrabold text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="closeFilterModal">
                                Скасувати
                            </button>
                            <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#343241] px-5 text-sm font-extrabold text-white hover:bg-[#24212f]" :disabled="filterForm.processing">
                                <Save class="h-4 w-4" />
                                Зберегти
                            </button>
                        </div>
                    </div>
                </form>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
