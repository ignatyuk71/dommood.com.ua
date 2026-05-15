<script setup>
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    Copy,
    Image,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
    categoryOptions: {
        type: Array,
        required: true,
    },
});

const productRows = ref([]);
const categoryId = ref(props.filters.category_id ? String(props.filters.category_id) : '');
const expandedProductIds = ref([]);
const categoryPickerOpenId = ref(null);
const productDrafts = reactive({});
const variantDrafts = reactive({});
const productStates = reactive({});
const variantStates = reactive({});
const productTimers = new Map();
const variantTimers = new Map();
const stateTimers = new Map();
const categoryFilterStorageKey = 'admin.products.category_id';

const statusValues = {
    active: 'active',
    draft: 'draft',
};

const cloneProduct = (product) => ({
    ...product,
    categories: [...(product.categories ?? [])],
    variants: (product.variants ?? []).map((variant) => ({ ...variant })),
});

const ensureProductDraft = (product) => {
    productDrafts[product.id] = {
        price: product.price ?? '0.00',
        old_price: product.old_price ?? '',
        status: product.status ?? statusValues.draft,
        stock_status: product.stock_status ?? 'in_stock',
        category_ids: (product.category_ids?.length
            ? product.category_ids
            : (product.categories ?? []).map((category) => category.id))
            .map((id) => Number(id)),
    };

    (product.variants ?? []).forEach((variant) => {
        variantDrafts[variant.id] = {
            size: variant.size ?? '',
            price: variant.price ?? '',
            stock_quantity: variant.stock_quantity ?? 0,
            is_active: Boolean(variant.is_active),
        };
    });
};

watch(
    () => props.products.data,
    (products) => {
        productRows.value = products.map(cloneProduct);
        productRows.value.forEach(ensureProductDraft);
    },
    { immediate: true },
);

const notify = (message, type = 'success') => {
    window.dispatchEvent(new CustomEvent('admin-toast', {
        detail: { message, type },
    }));
};

const clearStateLater = (bucket, id) => {
    clearTimeout(stateTimers.get(`${bucket}:${id}`));
    stateTimers.set(`${bucket}:${id}`, setTimeout(() => {
        if (bucket === 'product') {
            productStates[id] = null;
        } else {
            variantStates[id] = null;
        }
    }, 1400));
};

const setProductState = (id, state) => {
    productStates[id] = state;

    if (state === 'saved') {
        clearStateLater('product', id);
    }
};

const setVariantState = (id, state) => {
    variantStates[id] = state;

    if (state === 'saved') {
        clearStateLater('variant', id);
    }
};

const extractErrorMessage = (error, fallback) => {
    const payload = error.response?.data;

    if (payload?.errors) {
        const firstError = Object.values(payload.errors).flat().find(Boolean);

        if (firstError) {
            return firstError;
        }
    }

    return payload?.message ?? fallback;
};

const applyFilters = () => {
    if (categoryId.value) {
        window.localStorage.setItem(categoryFilterStorageKey, categoryId.value);
    } else {
        window.localStorage.removeItem(categoryFilterStorageKey);
    }

    router.get(route('admin.products.index'), {
        category_id: categoryId.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const isExpanded = (productId) => expandedProductIds.value.includes(productId);

const toggleProduct = (productId) => {
    expandedProductIds.value = isExpanded(productId)
        ? expandedProductIds.value.filter((id) => id !== productId)
        : [...expandedProductIds.value, productId];
};

const selectedCategoryIds = (product) => productDrafts[product.id]?.category_ids ?? [];

const selectedCategories = (product) => {
    const ids = selectedCategoryIds(product);

    return props.categoryOptions
        .filter((option) => ids.includes(Number(option.id)))
        .map((option) => ({
            id: option.id,
            name: option.name ?? option.label,
            slug: option.slug,
            label: option.label,
        }));
};

const isCategorySelected = (product, optionId) => selectedCategoryIds(product).includes(Number(optionId));

const toggleCategoryPicker = (productId) => {
    categoryPickerOpenId.value = categoryPickerOpenId.value === productId ? null : productId;
};

const toggleCategory = (product, optionId, event) => {
    const draft = productDrafts[product.id];
    const id = Number(optionId);
    const checked = event.target.checked;
    const currentIds = draft.category_ids ?? [];

    if (!checked && currentIds.length <= 1 && currentIds.includes(id)) {
        event.target.checked = true;
        notify('У товару має бути хоча б одна категорія.', 'warning');
        return;
    }

    draft.category_ids = checked
        ? [...new Set([...currentIds, id])]
        : currentIds.filter((categoryIdValue) => categoryIdValue !== id);

    product.categories = selectedCategories(product);
    product.categories_count = draft.category_ids.length;
    scheduleProductSave(product, 250);
};

const setProductActive = (product, event) => {
    productDrafts[product.id].status = event.target.checked ? statusValues.active : statusValues.draft;
    scheduleProductSave(product, 150);
};

const productPayload = (product) => {
    const draft = productDrafts[product.id];

    return {
        price: draft.price || '0',
        old_price: draft.old_price || null,
        status: draft.status,
        stock_status: draft.stock_status,
        category_ids_present: true,
        category_ids: draft.category_ids ?? [],
    };
};

const updateProductFromResponse = (product, payload) => {
    const currentVariants = product.variants ?? [];

    Object.assign(product, payload);
    product.variants = currentVariants;
    ensureProductDraft(product);
};

const saveProduct = async (product) => {
    clearTimeout(productTimers.get(product.id));
    setProductState(product.id, 'saving');

    try {
        const response = await axios.patch(route('admin.products.quick', product.id), productPayload(product), {
            headers: { Accept: 'application/json' },
        });

        updateProductFromResponse(product, response.data.product);
        setProductState(product.id, 'saved');
        notify(response.data.message ?? 'Товар оновлено');
    } catch (error) {
        setProductState(product.id, 'error');
        notify(extractErrorMessage(error, 'Не вдалося оновити товар'), 'error');
    }
};

const scheduleProductSave = (product, delay = 650) => {
    setProductState(product.id, 'dirty');
    clearTimeout(productTimers.get(product.id));
    productTimers.set(product.id, setTimeout(() => saveProduct(product), delay));
};

const variantPayload = (variant) => {
    const draft = variantDrafts[variant.id];

    return {
        size: draft.size || null,
        price: draft.price || null,
        stock_quantity: draft.stock_quantity || 0,
        is_active: Boolean(draft.is_active),
    };
};

const saveVariant = async (product, variant) => {
    clearTimeout(variantTimers.get(variant.id));
    setVariantState(variant.id, 'saving');

    try {
        const response = await axios.patch(
            route('admin.products.variants.update', [product.id, variant.id]),
            variantPayload(variant),
            { headers: { Accept: 'application/json' } },
        );

        Object.assign(variant, response.data.variant);
        variantDrafts[variant.id] = {
            size: variant.size ?? '',
            price: variant.price ?? '',
            stock_quantity: variant.stock_quantity ?? 0,
            is_active: Boolean(variant.is_active),
        };
        setVariantState(variant.id, 'saved');
        notify(response.data.message ?? 'Варіацію оновлено');
    } catch (error) {
        setVariantState(variant.id, 'error');
        notify(extractErrorMessage(error, 'Не вдалося оновити варіацію'), 'error');
    }
};

const scheduleVariantSave = (product, variant, delay = 650) => {
    setVariantState(variant.id, 'dirty');
    clearTimeout(variantTimers.get(variant.id));
    variantTimers.set(variant.id, setTimeout(() => saveVariant(product, variant), delay));
};

const destroyVariant = async (product, variant) => {
    if (!window.confirm(`Видалити варіацію #${variant.id}?`)) {
        return;
    }

    setVariantState(variant.id, 'saving');

    try {
        const response = await axios.delete(route('admin.products.variants.destroy', [product.id, variant.id]), {
            headers: { Accept: 'application/json' },
        });

        product.variants = product.variants.filter((item) => item.id !== variant.id);
        product.variants_count = response.data.variants_count ?? product.variants.length;
        delete variantDrafts[variant.id];
        setVariantState(variant.id, null);
        notify(response.data.message ?? 'Варіацію видалено');
    } catch (error) {
        setVariantState(variant.id, 'error');
        notify(extractErrorMessage(error, 'Не вдалося видалити варіацію'), 'error');
    }
};

const destroyProduct = (product) => {
    if (!window.confirm(`Видалити товар "${product.name}"?`)) {
        return;
    }

    router.delete(route('admin.products.destroy', product.id), {
        preserveScroll: true,
    });
};

const duplicateProduct = (product) => {
    router.post(route('admin.products.duplicate', product.id), {}, {
        preserveScroll: true,
    });
};

const parseMoney = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(String(value).replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : null;
};

const formatMoney = (value, currency = 'UAH') => {
    const parsed = parseMoney(value);

    if (parsed === null) {
        return '—';
    }

    const isInteger = Math.abs(parsed - Math.round(parsed)) < 0.001;
    const amount = new Intl.NumberFormat('uk-UA', {
        minimumFractionDigits: isInteger ? 0 : 2,
        maximumFractionDigits: 2,
    }).format(parsed);

    return currency === 'UAH' ? `${amount} грн` : `${amount} ${currency}`;
};

const formatPercent = (value) => {
    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        return '';
    }

    const rounded = Math.round(parsed * 100) / 100;

    return Math.abs(rounded - Math.round(rounded)) < 0.001
        ? String(Math.round(rounded))
        : rounded.toFixed(2);
};

const productPriceSummary = (product) => {
    const prices = (product.variants ?? [])
        .map((variant) => parseMoney(variantDrafts[variant.id]?.price ?? variant.price))
        .filter((price) => price !== null);

    if (!prices.length) {
        return productDrafts[product.id]?.old_price ? 'Є стара ціна' : '';
    }

    const minPrice = Math.min(...prices);
    const maxPrice = Math.max(...prices);

    if (minPrice === maxPrice) {
        return `Продаж: ${formatMoney(minPrice, product.currency)}`;
    }

    return `Варіації: ${formatMoney(minPrice, product.currency)} - ${formatMoney(maxPrice, product.currency)}`;
};

const variantDiscount = (product, variant) => {
    const basePrice = parseMoney(productDrafts[product.id]?.price ?? product.price);
    const variantPrice = parseMoney(variantDrafts[variant.id]?.price ?? variant.price);

    if (!basePrice || !variantPrice || variantPrice >= basePrice) {
        return '';
    }

    return `Знижка: ${formatPercent((1 - (variantPrice / basePrice)) * 100)}%`;
};

const isKnownCategory = (value) => props.categoryOptions.some((option) => String(option.id) === String(value));

watch(
    () => props.filters.category_id,
    (categoryIdFromServer) => {
        categoryId.value = categoryIdFromServer ? String(categoryIdFromServer) : '';
    },
);

onMounted(() => {
    if (categoryId.value) {
        window.localStorage.setItem(categoryFilterStorageKey, categoryId.value);
        return;
    }

    const savedCategoryId = window.localStorage.getItem(categoryFilterStorageKey);

    if (! savedCategoryId) {
        return;
    }

    if (! isKnownCategory(savedCategoryId)) {
        window.localStorage.removeItem(categoryFilterStorageKey);
        return;
    }

    categoryId.value = savedCategoryId;
    applyFilters();
});

onBeforeUnmount(() => {
    [...productTimers.values(), ...variantTimers.values(), ...stateTimers.values()].forEach((timer) => clearTimeout(timer));
});
</script>

<template>
    <Head title="Товари" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-[#343241]">Товари</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Каталог</p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <select
                        v-model="categoryId"
                        class="h-9 min-w-52 rounded-md border-slate-200 bg-white text-sm font-semibold text-slate-600 shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        @change="applyFilters"
                    >
                        <option value="">Усі категорії</option>
                        <option v-for="option in categoryOptions" :key="option.id" :value="String(option.id)">
                            {{ option.label }}
                        </option>
                    </select>
                    <Link
                        :href="route('admin.products.create')"
                        class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_10px_22px_rgba(117,97,247,0.24)] transition hover:bg-[#6552e8]"
                    >
                        <Plus class="h-4 w-4" />
                        Додати товар
                    </Link>
                </div>
            </div>
        </template>

        <section class="space-y-2">
            <div class="hidden rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 xl:grid xl:grid-cols-[minmax(360px,1fr)_230px_210px_96px_132px_104px] xl:gap-4">
                <div>Товар</div>
                <div>Категорії</div>
                <div>Ціна</div>
                <div>Статус</div>
                <div>Мітки</div>
                <div class="text-right">Дії</div>
            </div>

            <div v-if="productRows.length" class="space-y-2">
                <article
                    v-for="product in productRows"
                    :key="product.id"
                    class="overflow-visible rounded-lg border bg-white shadow-[0_8px_22px_rgba(15,23,42,0.045)] transition hover:border-slate-300 hover:shadow-[0_12px_28px_rgba(15,23,42,0.07)]"
                    :class="[
                        productStates[product.id] === 'saving' ? 'border-[#d9d1ff] bg-[#fbfaff]' : 'border-slate-200',
                        productStates[product.id] === 'error' ? 'border-rose-200 bg-rose-50/50' : '',
                    ]"
                >
                    <div
                        class="grid cursor-pointer items-center gap-3 px-3 py-2.5 lg:grid-cols-[minmax(340px,1fr)_230px_210px_96px_132px_104px] lg:gap-4"
                        @click="toggleProduct(product.id)"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-md bg-slate-100 ring-1 ring-slate-200">
                                <img
                                    v-if="product.main_image_url"
                                    :src="product.main_image_url"
                                    :alt="product.name"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center text-slate-400">
                                    <Image class="h-5 w-5" />
                                </div>
                                <span class="absolute left-1 top-1 rounded bg-white/90 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-600 shadow-sm">
                                    #{{ product.id }}
                                </span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-2">
                                    <a
                                        :href="product.public_url"
                                        class="block max-h-10 overflow-hidden text-sm font-bold leading-5 text-[#242231] transition hover:text-[#7561f7] hover:underline"
                                        target="_blank"
                                        rel="noopener"
                                        @click.stop
                                    >
                                        {{ product.name || 'Без назви' }}
                                    </a>
                                    <ChevronDown
                                        class="hidden h-4 w-4 shrink-0 text-slate-400 transition sm:block"
                                        :class="isExpanded(product.id) ? 'rotate-180 text-[#7561f7]' : ''"
                                    />
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] font-semibold text-slate-500">
                                    <span class="font-mono text-slate-700">{{ product.sku || 'Без SKU' }}</span>
                                    <span class="h-1 w-1 rounded-full bg-slate-300" />
                                    <span>{{ product.variants?.length ?? 0 }} вар.</span>
                                    <span v-if="productPriceSummary(product)" class="hidden sm:inline">{{ productPriceSummary(product) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="relative min-w-0" @click.stop>
                            <button
                                type="button"
                                class="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 text-left text-xs font-semibold text-slate-700 transition hover:border-[#7561f7]/50 hover:bg-white"
                                @click="toggleCategoryPicker(product.id)"
                            >
                                <span class="flex min-w-0 flex-wrap gap-1">
                                    <template v-if="selectedCategories(product).length">
                                        <span
                                            v-for="category in selectedCategories(product).slice(0, 2)"
                                            :key="category.id"
                                            class="max-w-[96px] truncate rounded bg-white px-1.5 py-0.5 text-[11px] font-bold text-[#4c51bf] ring-1 ring-[#dfe5ff]"
                                        >
                                            {{ category.name }}
                                        </span>
                                        <span
                                            v-if="selectedCategories(product).length > 2"
                                            class="rounded bg-white px-1.5 py-0.5 text-[11px] font-bold text-slate-500 ring-1 ring-slate-200"
                                        >
                                            +{{ selectedCategories(product).length - 2 }}
                                        </span>
                                    </template>
                                    <span v-else class="text-xs font-semibold text-slate-400">Категорія</span>
                                </span>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-slate-400 transition"
                                    :class="categoryPickerOpenId === product.id ? 'rotate-180' : ''"
                                />
                            </button>

                            <div
                                v-if="categoryPickerOpenId === product.id"
                                class="absolute left-0 top-10 z-30 w-72 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.14)]"
                            >
                                <div class="border-b border-slate-100 bg-slate-50 px-3 py-2">
                                    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Категорії товару</div>
                                    <div class="mt-0.5 text-[11px] font-semibold text-slate-400">Можна вибрати кілька значень</div>
                                </div>
                                <div class="max-h-64 overflow-y-auto p-1.5">
                                    <label
                                        v-for="option in categoryOptions"
                                        :key="option.id"
                                        class="flex cursor-pointer items-start gap-2 rounded-md px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                                            :checked="isCategorySelected(product, option.id)"
                                            @change="toggleCategory(product, option.id, $event)"
                                        />
                                        <span>{{ option.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div v-if="productDrafts[product.id]" class="min-w-0" @click.stop>
                            <div class="flex rounded-md shadow-sm">
                                <input
                                    v-model="productDrafts[product.id].price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="h-9 w-full rounded-l-md border-slate-200 bg-white text-sm font-bold text-[#242231] focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    @input="scheduleProductSave(product)"
                                />
                                <span class="inline-flex h-9 items-center rounded-r-md border border-l-0 border-slate-200 bg-slate-50 px-2.5 text-[11px] font-bold text-slate-500">грн</span>
                            </div>
                        </div>

                        <div v-if="productDrafts[product.id]" class="flex items-center gap-2" @click.stop>
                            <label class="inline-flex items-center">
                                <input
                                    type="checkbox"
                                    class="peer sr-only"
                                    :checked="productDrafts[product.id].status === 'active'"
                                    @change="setProductActive(product, $event)"
                                />
                                <span class="h-5 w-9 rounded-full bg-slate-200 p-0.5 transition peer-checked:bg-[#7561f7]">
                                    <span
                                        class="block h-4 w-4 rounded-full bg-white shadow transition"
                                        :class="productDrafts[product.id].status === 'active' ? 'translate-x-4' : ''"
                                    />
                                </span>
                            </label>
                            <span class="text-[11px] font-bold text-slate-500">
                                {{ productDrafts[product.id].status === 'active' ? 'Активний' : 'Чернетка' }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-1">
                            <span v-if="product.is_new" class="rounded bg-sky-50 px-2 py-0.5 text-[11px] font-bold text-sky-700">New</span>
                            <span v-if="product.is_featured" class="rounded bg-[#f5f4ff] px-2 py-0.5 text-[11px] font-bold text-[#7561f7]">Rec</span>
                            <span v-if="product.is_bestseller" class="rounded bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700">Hit</span>
                            <span v-if="!product.is_new && !product.is_featured && !product.is_bestseller" class="text-sm font-semibold text-slate-400">—</span>
                        </div>

                        <div class="flex justify-end gap-1.5">
                            <Link
                                :href="route('admin.products.edit', product.id)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                aria-label="Редагувати"
                                title="Редагувати"
                                @click.stop
                            >
                                <Pencil class="h-4 w-4" />
                            </Link>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                aria-label="Дублювати"
                                title="Дублювати"
                                @click.stop="duplicateProduct(product)"
                            >
                                <Copy class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-red-300 hover:text-red-600"
                                aria-label="Видалити"
                                title="Видалити"
                                @click.stop="destroyProduct(product)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div v-if="isExpanded(product.id)" class="border-t border-slate-100 bg-slate-50 px-3 py-3">
                        <div v-if="product.variants?.length" class="overflow-x-auto">
                            <div class="min-w-[720px]">
                                <div class="mb-2 grid grid-cols-[64px_1fr_130px_150px_110px_100px_42px] gap-2 px-2 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                    <div>ID</div>
                                    <div>SKU</div>
                                    <div>Розмір</div>
                                    <div>Ціна</div>
                                    <div>Знижка</div>
                                    <div>Залишок</div>
                                    <div />
                                </div>

                                <div class="space-y-1.5">
                                    <form
                                        v-for="variant in product.variants"
                                        :key="variant.id"
                                        class="grid grid-cols-[64px_1fr_130px_150px_110px_100px_42px] items-center gap-2 rounded-md border bg-white px-2 py-1.5"
                                        :class="[
                                            variantStates[variant.id] === 'dirty' ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200',
                                            variantStates[variant.id] === 'saving' ? 'border-[#d9d1ff] bg-[#f7f5ff]' : '',
                                            variantStates[variant.id] === 'error' ? 'border-rose-200 bg-rose-50/50' : '',
                                        ]"
                                        @submit.prevent="saveVariant(product, variant)"
                                        @click.stop
                                    >
                                        <div class="font-mono text-xs font-bold text-[#4451b8]">#{{ variant.id }}</div>
                                        <div class="min-w-0 truncate font-mono text-xs font-bold text-[#343241]">
                                            {{ variant.sku || product.sku || 'Без SKU' }}
                                        </div>
                                        <div v-if="variantDrafts[variant.id]">
                                            <input
                                                v-model="variantDrafts[variant.id].size"
                                                type="text"
                                                class="h-8 w-full rounded-md border-slate-200 text-sm font-semibold text-[#343241] focus:border-[#7561f7] focus:ring-[#7561f7]"
                                                placeholder="36-37"
                                                @input="scheduleVariantSave(product, variant)"
                                            />
                                        </div>
                                        <div v-if="variantDrafts[variant.id]">
                                            <div class="flex rounded-md shadow-sm">
                                                <input
                                                    v-model="variantDrafts[variant.id].price"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="h-8 w-full rounded-l-md border-slate-200 text-sm font-semibold text-[#343241] focus:border-[#7561f7] focus:ring-[#7561f7]"
                                                    @input="scheduleVariantSave(product, variant)"
                                                />
                                                <span class="inline-flex h-8 items-center rounded-r-md border border-l-0 border-slate-200 bg-slate-50 px-2 text-xs font-semibold text-slate-500">грн</span>
                                            </div>
                                        </div>
                                        <div>
                                            <span
                                                v-if="variantDiscount(product, variant)"
                                                class="inline-flex h-7 items-center rounded bg-[#eef2ff] px-2 text-[11px] font-bold text-[#4451b8]"
                                            >
                                                {{ variantDiscount(product, variant) }}
                                            </span>
                                            <span v-else class="text-xs font-semibold text-slate-300">—</span>
                                        </div>
                                        <div v-if="variantDrafts[variant.id]">
                                            <input
                                                v-model.number="variantDrafts[variant.id].stock_quantity"
                                                type="number"
                                                min="0"
                                                step="1"
                                                class="h-8 w-full rounded-md border-slate-200 text-sm font-semibold focus:border-[#7561f7] focus:ring-[#7561f7]"
                                                :class="variantDrafts[variant.id].stock_quantity > 0 ? 'text-[#343241]' : 'border-rose-200 bg-rose-50 text-rose-700'"
                                                @input="scheduleVariantSave(product, variant)"
                                            />
                                        </div>
                                        <div class="flex justify-end">
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-600 transition hover:border-red-300 hover:text-red-600"
                                                aria-label="Видалити варіацію"
                                                title="Видалити варіацію"
                                                @click="destroyVariant(product, variant)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div v-else class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-3 text-sm font-semibold text-slate-500">
                            Варіацій ще немає.
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm font-semibold text-slate-500">
                Товарів ще немає.
            </div>

            <div v-if="products.links?.length > 3" class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3">
                <template v-for="link in products.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg px-3 py-2 text-sm font-bold transition"
                        :class="link.active ? 'bg-[#7561f7] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-bold text-slate-300"
                        v-html="link.label"
                    />
                </template>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
