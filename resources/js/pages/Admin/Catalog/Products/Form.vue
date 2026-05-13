<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    GripVertical,
    ImagePlus,
    Plus,
    Save,
    Search,
    Wand2,
    Trash2,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    product: {
        type: Object,
        required: true,
    },
    categoryOptions: {
        type: Array,
        required: true,
    },
    colorGroupOptions: {
        type: Array,
        required: true,
    },
    sizeChartOptions: {
        type: Array,
        required: true,
    },
    attributeOptions: {
        type: Array,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
    stockStatusOptions: {
        type: Array,
        required: true,
    },
});

const isEdit = computed(() => props.mode === 'edit');
const fileInput = ref(null);
const objectUrls = ref([]);
const dragImageIndex = ref(null);
const dragAttributeIndex = ref(null);
const dragVariantIndex = ref(null);

const uid = () => (window.crypto?.randomUUID?.() ?? `uid-${Date.now()}-${Math.random().toString(16).slice(2)}`);

const money = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number.parseFloat(String(value).replace(',', '.'));

    return Number.isFinite(number) ? number : null;
};

const basePriceInitial = props.product.old_price || props.product.price || '0.00';
const salePriceInitial = props.product.old_price ? props.product.price : '';

const form = useForm({
    primary_category_id: props.product.primary_category_id ?? '',
    category_ids: props.product.category_ids ?? [],
    color_group_id: props.product.color_group_id ?? '',
    size_chart_id: props.product.size_chart_id ?? '',
    name: props.product.name ?? '',
    slug: props.product.slug ?? '',
    sku: props.product.sku ?? '',
    short_description: props.product.short_description ?? '',
    description: props.product.description ?? '',
    status: props.product.status ?? 'draft',
    base_price: basePriceInitial,
    sale_price: salePriceInitial,
    price: props.product.price ?? '0.00',
    old_price: props.product.old_price ?? '',
    cost_price: props.product.cost_price ?? '',
    currency: props.product.currency ?? 'UAH',
    stock_status: props.product.stock_status ?? 'in_stock',
    is_featured: props.product.is_featured ?? false,
    is_new: props.product.is_new ?? false,
    is_bestseller: props.product.is_bestseller ?? false,
    color_sort_order: props.product.color_sort_order ?? 0,
    sort_order: props.product.sort_order ?? 0,
    meta_title: props.product.meta_title ?? '',
    meta_description: props.product.meta_description ?? '',
    seo_text: props.product.seo_text ?? '',
    canonical_url: props.product.canonical_url ?? '',
    published_at: props.product.published_at ?? '',
    attribute_value_ids: props.product.attribute_value_ids ?? [],
    variants: [],
    images: [],
    new_image_keys: [],
    image_order: [],
    delete_image_ids: [],
});

const SEO_TITLE_LIMIT = 60;
const SEO_DESCRIPTION_LIMIT = 160;
const publicOrigin = () => (typeof window === 'undefined' ? 'https://dommood.com.ua' : window.location.origin);

const normalizeSeoText = (value) => String(value ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const truncateSeoPreview = (value, limit) => {
    const normalized = normalizeSeoText(value);

    if (normalized.length <= limit) {
        return normalized;
    }

    return `${normalized.slice(0, Math.max(0, limit - 1)).trim()}…`;
};

const selectedPrimaryCategory = computed(() => props.categoryOptions
    .find((category) => Number(category.id) === Number(form.primary_category_id)) ?? null);

const generatedProductSlug = computed(() => {
    const slug = normalizeSeoText(form.slug);

    if (slug) {
        return slug;
    }

    const generated = transliterate(normalizeSeoText(form.name))
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-');

    return generated || 'product-slug';
});

const seoPreviewTitle = computed(() => truncateSeoPreview(
    form.meta_title || form.name || 'Назва товару',
    SEO_TITLE_LIMIT,
));

const seoPreviewDescription = computed(() => truncateSeoPreview(
    form.meta_description || form.short_description || form.description || 'Короткий опис товару з ключовою перевагою, ціною або умовою доставки зʼявиться тут.',
    SEO_DESCRIPTION_LIMIT,
));

const seoPreviewUrl = computed(() => {
    const canonicalUrl = normalizeSeoText(form.canonical_url);

    if (canonicalUrl) {
        if (/^https?:\/\//i.test(canonicalUrl)) {
            return canonicalUrl;
        }

        return `${publicOrigin()}/${canonicalUrl.replace(/^\/+/, '')}`;
    }

    const categorySlug = selectedPrimaryCategory.value?.slug || 'category';

    return `${publicOrigin()}/catalog/${categorySlug}/${generatedProductSlug.value}`;
});

const seoPreviewDisplayUrl = computed(() => seoPreviewUrl.value
    .replace(/^https?:\/\//i, '')
    .replace(/\/$/, ''));

const seoTitleState = computed(() => {
    const length = normalizeSeoText(form.meta_title).length;

    if (!length) {
        return 'empty';
    }

    return length <= SEO_TITLE_LIMIT ? 'good' : 'over';
});

const seoDescriptionState = computed(() => {
    const length = normalizeSeoText(form.meta_description).length;

    if (!length) {
        return 'empty';
    }

    return length <= SEO_DESCRIPTION_LIMIT ? 'good' : 'over';
});

const seoStateClass = (state) => ({
    empty: 'text-slate-400',
    good: 'text-emerald-600',
    over: 'text-red-500',
}[state] ?? 'text-slate-400');

const fillSeoFromProduct = () => {
    if (!normalizeSeoText(form.meta_title)) {
        form.meta_title = truncateSeoPreview(form.name, SEO_TITLE_LIMIT);
    }

    if (!normalizeSeoText(form.meta_description)) {
        form.meta_description = truncateSeoPreview(
            form.short_description || form.description || form.name,
            SEO_DESCRIPTION_LIMIT,
        );
    }
};

const isActive = computed({
    get: () => form.status === 'active',
    set: (value) => {
        form.status = value ? 'active' : 'draft';
    },
});

const existingImages = ref((props.product.images ?? []).map((image) => ({
    ...image,
    key: `e:${image.id}`,
})));

const newImages = ref([]);
const galleryOrder = ref(existingImages.value.map((image) => image.key));

const attributeRows = ref((props.product.attribute_rows?.length ? props.product.attribute_rows : [])
    .map((row) => ({
        uid: uid(),
        attribute_id: row.attribute_id ?? null,
        attribute_value_id: row.attribute_value_id ?? null,
        is_editing: false,
    })));

if (!attributeRows.value.length && form.attribute_value_ids.length) {
    attributeRows.value = form.attribute_value_ids.map((valueId) => {
        const attribute = props.attributeOptions.find((item) => item.values.some((value) => value.id === valueId));

        return {
            uid: uid(),
            attribute_id: attribute?.id ?? null,
            attribute_value_id: valueId,
            is_editing: false,
        };
    }).filter((row) => row.attribute_id);
}

const attributeDraft = ref({
    attribute_id: '',
    attribute_value_id: '',
});

const extractSkuSuffix = (sku) => {
    const baseSku = String(form.sku || '').trim();
    const fullSku = String(sku || '').trim();

    if (!baseSku || !fullSku) {
        return fullSku;
    }

    const prefix = `${baseSku}-`;

    return fullSku.startsWith(prefix) ? fullSku.slice(prefix.length) : fullSku;
};

const variants = ref((props.product.variants ?? []).map((variant) => ({
    uid: uid(),
    id: variant.id ?? null,
    sku_suffix: extractSkuSuffix(variant.sku),
    size: variant.size ?? '',
    price: variant.price ?? '',
    stock_quantity: variant.stock_quantity ?? '',
    is_active: variant.is_active ?? true,
})));

const selectedSizeChart = computed(() => props.sizeChartOptions.find((chart) => chart.id === Number(form.size_chart_id)) ?? null);
const sizeChartContent = computed(() => selectedSizeChart.value?.content ?? null);
const sizeChartColumns = computed(() => Array.isArray(sizeChartContent.value?.columns) ? sizeChartContent.value.columns : []);
const sizeChartRows = computed(() => Array.isArray(sizeChartContent.value?.rows) ? sizeChartContent.value.rows : []);

const salePercent = computed(() => {
    const base = money(form.base_price);
    const sale = money(form.sale_price);

    if (!base || !sale || sale >= base) {
        return '';
    }

    const percent = (1 - (sale / base)) * 100;

    return `${Number.isInteger(percent) ? percent : percent.toFixed(2)}%`;
});

const selectedCategoryLabels = computed(() => props.categoryOptions
    .filter((category) => form.category_ids.map(Number).includes(Number(category.id)))
    .map((category) => category.label));

const galleryItems = computed(() => {
    const existingMap = new Map(existingImages.value
        .filter((image) => !form.delete_image_ids.includes(image.id))
        .map((image) => [image.key, {
            ...image,
            type: 'existing',
            preview: image.url,
        }]));
    const newMap = new Map(newImages.value.map((image) => [image.key, {
        ...image,
        type: 'new',
        preview: image.url,
    }]));

    return galleryOrder.value
        .map((key) => existingMap.get(key) ?? newMap.get(key) ?? null)
        .filter(Boolean);
});

const availableAttributesFor = (row = null) => {
    const used = new Set(attributeRows.value
        .filter((item) => item.uid !== row?.uid)
        .map((item) => Number(item.attribute_id))
        .filter(Boolean));

    return props.attributeOptions.filter((attribute) => !used.has(Number(attribute.id)));
};

const attributeValuesFor = (attributeId) => {
    const attribute = props.attributeOptions.find((item) => Number(item.id) === Number(attributeId));

    return attribute?.values ?? [];
};

const attributeName = (row) => props.attributeOptions.find((attribute) => Number(attribute.id) === Number(row.attribute_id))?.name ?? '—';
const attributeValueLabel = (row) => attributeValuesFor(row.attribute_id)
    .find((value) => Number(value.id) === Number(row.attribute_value_id))?.value ?? '—';

const resetAttributeDraft = () => {
    attributeDraft.value = {
        attribute_id: '',
        attribute_value_id: '',
    };
};

const addAttribute = () => {
    if (!attributeDraft.value.attribute_id || !attributeDraft.value.attribute_value_id) {
        return;
    }

    attributeRows.value.push({
        uid: uid(),
        attribute_id: Number(attributeDraft.value.attribute_id),
        attribute_value_id: Number(attributeDraft.value.attribute_value_id),
        is_editing: false,
    });
    resetAttributeDraft();
};

const removeAttribute = (index) => {
    attributeRows.value.splice(index, 1);
};

const addVariant = () => {
    variants.value.push({
        uid: uid(),
        id: null,
        sku_suffix: '',
        size: '',
        price: '',
        stock_quantity: '',
        is_active: true,
    });
};

const removeVariant = (index) => {
    variants.value.splice(index, 1);
};

const composeVariantSku = (variant) => {
    const baseSku = String(form.sku || '').trim();
    let suffix = String(variant.sku_suffix || '').trim();

    if (baseSku && suffix.startsWith(`${baseSku}-`)) {
        suffix = suffix.slice(baseSku.length + 1);
    }

    if (baseSku && suffix) {
        return `${baseSku}-${suffix}`;
    }

    return baseSku || suffix || '';
};

const moveItem = (items, from, to) => {
    if (from === null || from === to) {
        return;
    }

    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
};

const onImageDrop = (index) => {
    const nextOrder = moveItem(galleryItems.value.map((item) => item.key), dragImageIndex.value, index);

    if (nextOrder) {
        galleryOrder.value = nextOrder;
    }

    dragImageIndex.value = null;
};

const onAttributeDrop = (index) => {
    const nextRows = moveItem(attributeRows.value, dragAttributeIndex.value, index);

    if (nextRows) {
        attributeRows.value = nextRows;
    }

    dragAttributeIndex.value = null;
};

const onVariantDrop = (index) => {
    const nextRows = moveItem(variants.value, dragVariantIndex.value, index);

    if (nextRows) {
        variants.value = nextRows;
    }

    dragVariantIndex.value = null;
};

const openImageDialog = () => {
    fileInput.value?.click();
};

const addImageFiles = (fileList) => {
    const files = Array.from(fileList ?? []).filter((file) => file?.type?.startsWith('image/'));

    files.forEach((file) => {
        const url = URL.createObjectURL(file);
        const imageKey = `n:${uid()}`;
        objectUrls.value.push(url);
        newImages.value.push({
            key: imageKey,
            uid: imageKey.replace('n:', ''),
            file,
            url,
            name: file.name,
        });
        galleryOrder.value.push(imageKey);
    });
};

const onImagesSelected = (event) => {
    addImageFiles(event.target.files);
    event.target.value = '';
};

const removeImage = (item) => {
    if (item.type === 'existing') {
        form.delete_image_ids = [...new Set([...form.delete_image_ids, item.id])];
    }

    if (item.type === 'new') {
        const image = newImages.value.find((newImage) => newImage.key === item.key);

        if (image?.url) {
            URL.revokeObjectURL(image.url);
            objectUrls.value = objectUrls.value.filter((url) => url !== image.url);
        }

        newImages.value = newImages.value.filter((newImage) => newImage.key !== item.key);
    }

    galleryOrder.value = galleryOrder.value.filter((key) => key !== item.key);
};

const transliterate = (value) => value
    .toLowerCase()
    .replaceAll('є', 'ie')
    .replaceAll('і', 'i')
    .replaceAll('ї', 'i')
    .replaceAll('ґ', 'g')
    .replace(/[а-яё]/g, (char) => ({
        а: 'a', б: 'b', в: 'v', г: 'h', д: 'd', е: 'e', ж: 'zh', з: 'z',
        и: 'y', й: 'i', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p',
        р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch',
        ш: 'sh', щ: 'shch', ь: '', ю: 'iu', я: 'ia', ё: 'e',
    }[char] ?? ''));

const generateSlug = () => {
    form.slug = transliterate(form.name)
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .replace(/-{2,}/g, '-');
};

const resolvePrices = () => {
    const base = money(form.base_price);
    const sale = money(form.sale_price);

    if (base !== null && sale !== null && sale > 0 && sale < base) {
        return {
            price: sale.toFixed(2),
            old_price: base.toFixed(2),
        };
    }

    return {
        price: (base ?? 0).toFixed(2),
        old_price: '',
    };
};

const submit = () => {
    const prices = resolvePrices();
    const categoryIds = [...new Set([
        ...form.category_ids.map((id) => Number(id)),
        Number(form.primary_category_id),
    ].filter(Boolean))];
    const attributeValueIds = attributeRows.value
        .map((row) => Number(row.attribute_value_id))
        .filter(Boolean);
    const variantsPayload = variants.value.map((variant, index) => ({
        id: variant.id || '',
        sku: composeVariantSku(variant),
        size: variant.size,
        price: variant.price,
        stock_quantity: variant.stock_quantity,
        is_active: variant.is_active ? 1 : 0,
        sort_order: index * 10,
    }));

    form
        .transform((data) => {
            const payload = {
                ...data,
                primary_category_id: data.primary_category_id || '',
                category_ids: categoryIds,
                color_group_id: data.color_group_id || '',
                size_chart_id: data.size_chart_id || '',
                price: prices.price,
                old_price: prices.old_price,
                description: data.description ?? '',
                attribute_value_ids: attributeValueIds,
                variants: variantsPayload,
                images: newImages.value.map((image) => image.file),
                new_image_keys: newImages.value.map((image) => image.uid),
                image_order: galleryOrder.value,
                is_featured: data.is_featured ? 1 : 0,
                is_new: data.is_new ? 1 : 0,
                is_bestseller: data.is_bestseller ? 1 : 0,
            };

            if (isEdit.value) {
                payload._method = 'put';
            }

            return payload;
        })
        .post(
            isEdit.value
                ? route('admin.products.update', props.product.id)
                : route('admin.products.store'),
            {
                forceFormData: true,
            },
        );
};

onBeforeUnmount(() => {
    objectUrls.value.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <Head :title="isEdit ? 'Редагування товару' : 'Створення товару'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">
                        {{ isEdit ? 'Редагування товару' : 'Створення товару' }}
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-3 py-1 text-xs font-bold"
                        :class="isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                    >
                        {{ isActive ? 'Активний' : 'Чернетка' }}
                    </span>
                    <label class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600">
                        <input v-model="isActive" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                        Активний
                    </label>
                    <Link
                        :href="route('admin.products.index')"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Скасувати
                    </Link>
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_12px_28px_rgba(117,97,247,0.28)] transition hover:bg-[#6552e8] disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        <Save class="h-4 w-4" />
                        Зберегти
                    </button>
                </div>
            </div>
        </template>

        <form class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" @submit.prevent="submit">
            <section class="space-y-5">
                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-[#343241]">Основне</h2>
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-12">
                        <div class="md:col-span-8">
                            <label class="text-sm font-bold text-slate-700" for="name">Назва товару</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="md:col-span-4">
                            <label class="text-sm font-bold text-slate-700" for="sku">SKU</label>
                            <input
                                id="sku"
                                v-model="form.sku"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="DM-HAL-001"
                            />
                            <InputError class="mt-2" :message="form.errors.sku" />
                        </div>

                        <div class="md:col-span-12">
                            <label class="text-sm font-bold text-slate-700" for="slug">URL</label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="slug"
                                    v-model="form.slug"
                                    type="text"
                                    class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="zhinochi-domashni-kaptsi"
                                />
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                    @click="generateSlug"
                                >
                                    Slug
                                </button>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-slate-500">Якщо поле порожнє — URL сформується з назви.</p>
                            <InputError class="mt-2" :message="form.errors.slug" />
                        </div>

                        <div class="md:col-span-12">
                            <label class="text-sm font-bold text-slate-700" for="short_description">Короткий опис</label>
                            <textarea
                                id="short_description"
                                v-model="form.short_description"
                                rows="3"
                                maxlength="500"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            />
                            <InputError class="mt-2" :message="form.errors.short_description" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 md:flex-row md:items-center md:justify-between">
                        <h2 class="text-lg font-bold text-[#343241]">Галерея</h2>
                        <p class="text-sm font-semibold text-slate-500">Перше фото — головне.</p>
                    </div>

                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="sr-only"
                        @change="onImagesSelected"
                    />

                    <button
                        type="button"
                        class="mt-5 flex min-h-36 w-full items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-[#faf9ff] text-center transition hover:border-[#7561f7] hover:shadow-[0_10px_24px_rgba(117,97,247,0.12)]"
                        @click="openImageDialog"
                        @dragover.prevent
                        @drop.prevent="addImageFiles($event.dataTransfer.files)"
                    >
                        <span class="flex flex-col items-center gap-3 px-6 py-8 text-sm font-bold text-[#343241]">
                            <ImagePlus class="h-7 w-7 text-[#7561f7]" />
                            Перетягни файли або натисни для вибору
                            <span class="text-xs font-semibold text-slate-500">JPG, PNG або WebP до 6 MB</span>
                        </span>
                    </button>

                    <div v-if="galleryItems.length" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="(item, index) in galleryItems"
                            :key="item.key"
                            draggable="true"
                            class="group relative overflow-hidden rounded-xl border border-slate-100 bg-white p-1 shadow-[0_6px_16px_rgba(15,23,42,0.08)]"
                            @dragstart="dragImageIndex = index"
                            @dragover.prevent
                            @drop="onImageDrop(index)"
                        >
                            <img :src="item.preview" :alt="item.alt || item.name || form.name" class="aspect-square w-full rounded-lg object-cover" />
                            <button
                                type="button"
                                class="absolute right-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-white shadow-[0_6px_16px_rgba(255,77,79,0.35)]"
                                aria-label="Видалити фото"
                                @click="removeImage(item)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                            <span class="absolute bottom-3 left-3 rounded-lg bg-[#7561f7] px-2 py-1 text-xs font-bold text-white">#{{ index + 1 }}</span>
                            <span v-if="index === 0" class="absolute bottom-3 right-3 rounded-lg bg-[#7561f7] px-2 py-1 text-xs font-bold text-white">Головне</span>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.images" />
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-[#343241]">Характеристики</h2>
                        <p class="mt-1 text-sm text-slate-500">Ці значення потрапляють у фільтри й SEO URL категорій.</p>
                    </div>

                    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(260px,0.8fr)_minmax(0,1.2fr)]">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <label class="text-sm font-bold text-slate-700">Характеристика</label>
                            <select
                                v-model="attributeDraft.attribute_id"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                @change="attributeDraft.attribute_value_id = ''"
                            >
                                <option value="">Оберіть характеристику</option>
                                <option v-for="attribute in availableAttributesFor()" :key="attribute.id" :value="attribute.id">
                                    {{ attribute.name }}
                                </option>
                            </select>

                            <div class="mt-4">
                                <label class="text-sm font-bold text-slate-700">Значення</label>
                                <select
                                    v-model="attributeDraft.attribute_value_id"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    :disabled="!attributeDraft.attribute_id"
                                >
                                    <option value="">—</option>
                                    <option v-for="value in attributeValuesFor(attributeDraft.attribute_id)" :key="value.id" :value="value.id">
                                        {{ value.value }}
                                    </option>
                                </select>
                            </div>

                            <div class="mt-5 flex gap-2">
                                <button
                                    type="button"
                                    class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white disabled:opacity-50"
                                    :disabled="!attributeDraft.attribute_id || !attributeDraft.attribute_value_id"
                                    @click="addAttribute"
                                >
                                    <Plus class="h-4 w-4" />
                                    Додати
                                </button>
                                <button type="button" class="rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-600" @click="resetAttributeDraft">
                                    Скасувати
                                </button>
                            </div>
                        </div>

                        <div>
                            <div v-if="!attributeRows.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                                Характеристик ще немає.
                            </div>
                            <ul v-else class="space-y-3">
                                <li
                                    v-for="(row, index) in attributeRows"
                                    :key="row.uid"
                                    draggable="true"
                                    class="flex items-start gap-3 rounded-xl border border-slate-100 bg-white p-4 shadow-[0_6px_16px_rgba(15,23,42,0.08)]"
                                    @dragstart="dragAttributeIndex = index"
                                    @dragover.prevent
                                    @drop="onAttributeDrop(index)"
                                >
                                    <span class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#f3f2ff] text-[#7561f7]">
                                        <GripVertical class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-[#343241]">{{ attributeName(row) }}</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-500">{{ attributeValueLabel(row) }}</div>

                                        <div v-if="row.is_editing" class="mt-3 grid gap-3 md:grid-cols-2">
                                            <select
                                                v-model="row.attribute_id"
                                                class="rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                                @change="row.attribute_value_id = ''"
                                            >
                                                <option v-for="attribute in availableAttributesFor(row)" :key="attribute.id" :value="attribute.id">
                                                    {{ attribute.name }}
                                                </option>
                                            </select>
                                            <select
                                                v-model="row.attribute_value_id"
                                                class="rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                            >
                                                <option v-for="value in attributeValuesFor(row.attribute_id)" :key="value.id" :value="value.id">
                                                    {{ value.value }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 flex-col gap-2">
                                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600" @click="row.is_editing = !row.is_editing">
                                            {{ row.is_editing ? 'Закрити' : 'Редагувати' }}
                                        </button>
                                        <button type="button" class="rounded-lg border border-red-100 px-3 py-2 text-xs font-bold text-red-600" @click="removeAttribute(index)">
                                            Видалити
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.attribute_value_ids" />
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-bold text-[#343241]">Варіації</h2>
                        <button type="button" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-[#7561f7] px-4 text-sm font-bold text-[#7561f7]" @click="addVariant">
                            <Plus class="h-4 w-4" />
                            Додати варіацію
                        </button>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div v-if="!variants.length" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                            Варіацій ще немає.
                        </div>

                        <div
                            v-for="(variant, index) in variants"
                            :key="variant.uid"
                            draggable="true"
                            class="grid gap-3 rounded-xl border border-slate-100 bg-white p-4 shadow-[0_6px_16px_rgba(15,23,42,0.08)] xl:grid-cols-[36px_minmax(140px,1fr)_minmax(180px,1fr)_minmax(140px,1fr)_120px_auto_auto]"
                            @dragstart="dragVariantIndex = index"
                            @dragover.prevent
                            @drop="onVariantDrop(index)"
                        >
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[#f3f2ff] text-[#7561f7]">
                                <GripVertical class="h-4 w-4" />
                            </span>
                            <input v-model="variant.size" type="text" class="rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="Розмір 36-37" />
                            <div class="flex rounded-lg shadow-sm">
                                <span v-if="form.sku" class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-500">
                                    {{ form.sku }}
                                </span>
                                <input
                                    v-model="variant.sku_suffix"
                                    type="text"
                                    class="min-w-0 flex-1 rounded-lg border-slate-200 text-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    :class="form.sku ? 'rounded-l-none' : ''"
                                    placeholder="36 або RED"
                                />
                            </div>
                            <input v-model="variant.price" type="number" step="0.01" min="0" class="rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="Ціна продажу" />
                            <input v-model="variant.stock_quantity" type="number" min="0" class="rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="Залишок" />
                            <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                                <input v-model="variant.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                Активна
                            </label>
                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-100 text-red-600" @click="removeVariant(index)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-[#7561f7]">SEO</p>
                            <h2 class="mt-1 text-xl font-bold text-[#343241]">Пошуковий сніпет</h2>
                            <p class="mt-1 max-w-2xl text-sm font-semibold text-slate-500">
                                Це превʼю для Google і соцмереж. Якщо поля порожні — сайт візьме назву товару та короткий опис.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#7561f7]/30 bg-[#f7f5ff] px-4 text-sm font-bold text-[#5b47df] transition hover:border-[#7561f7] hover:bg-[#f0edff]"
                            @click="fillSeoFromProduct"
                        >
                            <Wand2 class="h-4 w-4" />
                            Заповнити порожні
                        </button>
                    </div>

                    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.86fr)]">
                        <div class="space-y-5">
                            <div>
                                <label class="flex items-center justify-between gap-3 text-sm font-bold text-slate-700" for="meta_title">
                                    <span>Meta Title, до {{ SEO_TITLE_LIMIT }} символів</span>
                                    <span class="text-xs font-black" :class="seoStateClass(seoTitleState)">
                                        {{ normalizeSeoText(form.meta_title).length }}/{{ SEO_TITLE_LIMIT }}
                                    </span>
                                </label>
                                <input
                                    id="meta_title"
                                    v-model="form.meta_title"
                                    type="text"
                                    :maxlength="SEO_TITLE_LIMIT"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="Короткий заголовок для Google"
                                />
                                <p class="mt-2 text-xs font-semibold text-slate-500">
                                    Додай ключову категорію, бренд або головну перевагу без переспаму.
                                </p>
                                <InputError class="mt-2" :message="form.errors.meta_title" />
                            </div>

                            <div>
                                <label class="flex items-center justify-between gap-3 text-sm font-bold text-slate-700" for="meta_description">
                                    <span>Meta Description, до {{ SEO_DESCRIPTION_LIMIT }} символів</span>
                                    <span class="text-xs font-black" :class="seoStateClass(seoDescriptionState)">
                                        {{ normalizeSeoText(form.meta_description).length }}/{{ SEO_DESCRIPTION_LIMIT }}
                                    </span>
                                </label>
                                <textarea
                                    id="meta_description"
                                    v-model="form.meta_description"
                                    rows="4"
                                    :maxlength="SEO_DESCRIPTION_LIMIT"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="Короткий опис товару для пошуку"
                                />
                                <p class="mt-2 text-xs font-semibold text-slate-500">
                                    Пиши вигоду для покупця: матеріал, сезонність, доставка або акція.
                                </p>
                                <InputError class="mt-2" :message="form.errors.meta_description" />
                            </div>

                            <div>
                                <label class="text-sm font-bold text-slate-700" for="canonical_url">Canonical URL</label>
                                <input
                                    id="canonical_url"
                                    v-model="form.canonical_url"
                                    type="text"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="/catalog/category/product-slug"
                                />
                                <p class="mt-2 text-xs font-semibold text-slate-500">
                                    Залиш порожнім, якщо канонічна сторінка — поточний товар у каталозі.
                                </p>
                                <InputError class="mt-2" :message="form.errors.canonical_url" />
                            </div>

                            <div>
                                <label class="text-sm font-bold text-slate-700" for="seo_text">SEO текст</label>
                                <textarea
                                    id="seo_text"
                                    v-model="form.seo_text"
                                    rows="5"
                                    class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="Додатковий текст для товарної сторінки, якщо він потрібен"
                                />
                                <InputError class="mt-2" :message="form.errors.seo_text" />
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_18px_48px_rgba(61,58,101,0.10)]">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Попередній вигляд</p>
                                    <h3 class="mt-1 text-base font-black text-[#343241]">Google snippet</h3>
                                </div>
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#f0edff] text-[#7561f7]">
                                    <Search class="h-5 w-5" />
                                </span>
                            </div>

                            <div class="mt-5 rounded-xl border border-slate-100 bg-[#fbfcff] p-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#eef2ff] text-xs font-black text-[#4c51bf]">
                                        DM
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-slate-700">DomMood</div>
                                        <div class="truncate text-xs font-medium text-slate-500">{{ seoPreviewDisplayUrl }}</div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="break-words text-xl font-medium leading-6 text-[#1a0dab]">
                                        {{ seoPreviewTitle }}
                                    </div>
                                    <div class="mt-1 break-words text-sm font-medium leading-6 text-[#12823b]">
                                        {{ seoPreviewDisplayUrl }}
                                    </div>
                                    <p class="mt-2 text-sm font-medium leading-6 text-slate-600">
                                        {{ seoPreviewDescription }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-2 text-xs font-bold text-slate-500 sm:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    Title:
                                    <span :class="seoStateClass(seoTitleState)">
                                        {{ seoTitleState === 'empty' ? 'fallback' : 'ok' }}
                                    </span>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    Description:
                                    <span :class="seoStateClass(seoDescriptionState)">
                                        {{ seoDescriptionState === 'empty' ? 'fallback' : 'ok' }}
                                    </span>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    URL:
                                    <span class="text-emerald-600">{{ selectedPrimaryCategory ? 'товар' : 'чернетка' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-5 xl:sticky xl:top-5 xl:self-start">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Ціни і наявність</h2>
                    <p class="mt-1 text-sm text-slate-500">Ціна зі знижкою стає поточною ціною товару.</p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="base_price">Базова ціна</label>
                            <input id="base_price" v-model="form.base_price" type="number" step="0.01" min="0" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" required />
                            <InputError class="mt-2" :message="form.errors.price" />
                        </div>
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="sale_price">Ціна зі знижкою</label>
                            <input id="sale_price" v-model="form.sale_price" type="number" step="0.01" min="0" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                        </div>
                        <div>
                            <label class="text-sm font-bold text-slate-700">% знижки</label>
                            <input :value="salePercent || '—'" type="text" readonly class="mt-2 w-full rounded-lg border-slate-200 bg-slate-50 text-sm font-bold text-slate-500 shadow-sm" />
                        </div>
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="currency">Валюта</label>
                            <input id="currency" v-model="form.currency" type="text" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                            <InputError class="mt-2" :message="form.errors.currency" />
                        </div>
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="stock_status">Наявність</label>
                            <select id="stock_status" v-model="form.stock_status" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]">
                                <option v-for="option in stockStatusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.stock_status" />
                        </div>

                        <div class="space-y-2 pt-1">
                            <label class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                                <span class="text-sm font-bold text-slate-700">Новинка</span>
                                <input v-model="form.is_new" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                            </label>
                            <label class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                                <span class="text-sm font-bold text-slate-700">Хіт продажу</span>
                                <input v-model="form.is_bestseller" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                            </label>
                            <label class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                                <span class="text-sm font-bold text-slate-700">Рекомендований</span>
                                <input v-model="form.is_featured" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                            </label>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Категорії та привʼязки</h2>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="primary_category_id">Основна категорія</label>
                            <select id="primary_category_id" v-model="form.primary_category_id" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" required>
                                <option value="">Вибери категорію</option>
                                <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                                    {{ category.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.primary_category_id" />
                        </div>

                        <div>
                            <div class="text-sm font-bold text-slate-700">Категорії</div>
                            <div class="mt-2 max-h-52 space-y-2 overflow-y-auto rounded-lg border border-slate-100 p-3">
                                <label v-for="category in categoryOptions" :key="category.id" class="flex items-start gap-2 text-sm font-semibold text-slate-600">
                                    <input v-model="form.category_ids" type="checkbox" :value="category.id" class="mt-0.5 rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                    <span>{{ category.label }}</span>
                                </label>
                            </div>
                            <div v-if="selectedCategoryLabels.length" class="mt-3 flex flex-wrap gap-2">
                                <span v-for="label in selectedCategoryLabels" :key="label" class="rounded-full bg-[#eef2ff] px-3 py-1 text-xs font-bold text-[#4c51bf]">
                                    {{ label }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="size_chart_id">Розмірна сітка</label>
                            <select id="size_chart_id" v-model="form.size_chart_id" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]">
                                <option value="">— немає —</option>
                                <option v-for="chart in sizeChartOptions" :key="chart.id" :value="chart.id">
                                    {{ chart.label }}
                                </option>
                            </select>

                            <div v-if="selectedSizeChart" class="mt-3 rounded-lg border border-slate-100 p-3">
                                <div class="mb-2 text-xs font-bold text-slate-500">Перевірка сітки: {{ selectedSizeChart.label }}</div>
                                <div v-if="sizeChartColumns.length && sizeChartRows.length" class="overflow-x-auto">
                                    <table class="min-w-full border-collapse text-xs">
                                        <thead>
                                            <tr>
                                                <th v-for="(column, index) in sizeChartColumns" :key="`column-${index}`" class="border border-slate-200 px-2 py-1 text-left">
                                                    {{ column }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(row, rowIndex) in sizeChartRows" :key="`row-${rowIndex}`">
                                                <td v-for="(cell, cellIndex) in row" :key="`cell-${rowIndex}-${cellIndex}`" class="border border-slate-200 px-2 py-1">
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="text-xs font-semibold text-slate-400">У цій сітці поки немає даних.</div>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="color_group_id">Група кольорів</label>
                            <select id="color_group_id" v-model="form.color_group_id" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]">
                                <option value="">— немає —</option>
                                <option v-for="group in colorGroupOptions" :key="group.id" :value="group.id">
                                    {{ group.label }}
                                </option>
                            </select>
                            <p class="mt-2 text-xs font-semibold text-slate-500">Обʼєднує різні кольори одного товару.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-sm font-bold text-slate-700" for="color_sort_order">Порядок кольору</label>
                                <input id="color_sort_order" v-model="form.color_sort_order" type="number" min="0" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-700" for="sort_order">Загальний порядок</label>
                                <input id="sort_order" v-model="form.sort_order" type="number" min="0" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h3 class="text-sm font-bold text-[#343241]">Підказка</h3>
                    <p class="mt-2 text-sm text-slate-500">
                        Для кольорів використовуй групу кольорів і порядок. Для фільтрів — характеристики з каталогу.
                    </p>
                </section>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
