<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Link2, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    attribute: {
        type: Object,
        required: true,
    },
    typeOptions: {
        type: Array,
        required: true,
    },
    filterExampleUrl: {
        type: String,
        required: true,
    },
});

const isEdit = computed(() => props.mode === 'edit');
const isColorType = computed(() => form.type === 'color');

const emptyValue = (sortOrder = 0) => ({
    id: null,
    value: '',
    slug: '',
    color_hex: '',
    sort_order: sortOrder,
});

const form = useForm({
    name: props.attribute.name ?? '',
    slug: props.attribute.slug ?? '',
    type: props.attribute.type ?? 'select',
    is_filterable: props.attribute.is_filterable ?? true,
    is_variant_option: props.attribute.is_variant_option ?? false,
    sort_order: props.attribute.sort_order ?? 0,
    values: props.attribute.values?.length ? props.attribute.values.map((value) => ({
        id: value.id ?? null,
        value: value.value ?? '',
        slug: value.slug ?? '',
        color_hex: value.color_hex ?? '',
        sort_order: value.sort_order ?? 0,
    })) : [emptyValue()],
});

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

const slugify = (value) => transliterate(value)
    .replace(/[^a-z0-9-]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-');

const generateSlug = () => {
    form.slug = slugify(form.name);
};

const generateValueSlug = (index) => {
    form.values[index].slug = slugify(form.values[index].value);
};

const addValue = () => {
    form.values.push(emptyValue(form.values.length * 10));
};

const removeValue = (index) => {
    if (form.values.length === 1) {
        form.values = [emptyValue()];
        return;
    }

    form.values.splice(index, 1);
};

const normalizeValues = () => {
    form.values = form.values.map((value) => ({
        ...value,
        color_hex: isColorType.value ? value.color_hex : '',
        sort_order: Number(value.sort_order || 0),
    }));
};

const previewUrl = computed(() => {
    const attributeSlug = form.slug || slugify(form.name) || 'material';
    const firstValue = form.values.find((value) => value.value || value.slug);
    const valueSlug = firstValue?.slug || slugify(firstValue?.value ?? '') || 'shtuchne-hutro';

    return `/catalog/kaptsi/filter/${attributeSlug}--${valueSlug}`;
});

const submit = () => {
    normalizeValues();

    if (isEdit.value) {
        form.put(route('admin.attributes.update', props.attribute.id));
        return;
    }

    form.post(route('admin.attributes.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Редагування характеристики' : 'Створення характеристики'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">
                        {{ isEdit ? 'Редагування характеристики' : 'Створення характеристики' }}
                    </h1>
                </div>

                <Link
                    :href="route('admin.attributes.index')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                >
                    <ArrowLeft class="h-4 w-4" />
                    До списку
                </Link>
            </div>
        </template>

        <form class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]" @submit.prevent="submit">
            <section class="space-y-5">
                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Основне</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Slug характеристики і slug значення стають частиною SEO URL фільтрів.
                    </p>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="name">Назва</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Напр., Матеріал"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="slug">Slug для URL</label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="slug"
                                    v-model="form.slug"
                                    type="text"
                                    class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="material"
                                />
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                    @click="generateSlug"
                                >
                                    Slug
                                </button>
                            </div>
                            <InputError class="mt-2" :message="form.errors.slug" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="type">Тип</label>
                            <select
                                id="type"
                                v-model="form.type"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option
                                    v-for="option in typeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.type" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="sort_order">Порядок</label>
                            <input
                                id="sort_order"
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            />
                            <InputError class="mt-2" :message="form.errors.sort_order" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-[#343241]">Значення</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Для фільтрів зберігаємо окремі значення, щоб URL були стабільні й індексовані.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                            @click="addValue"
                        >
                            <Plus class="h-4 w-4" />
                            Додати значення
                        </button>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full border-separate border-spacing-0">
                            <thead>
                                <tr>
                                    <th class="min-w-56 border-b border-slate-100 pb-3 pr-3 text-left text-xs font-bold uppercase text-slate-500">Назва</th>
                                    <th class="min-w-56 border-b border-slate-100 pb-3 pr-3 text-left text-xs font-bold uppercase text-slate-500">Slug</th>
                                    <th v-if="isColorType" class="min-w-40 border-b border-slate-100 pb-3 pr-3 text-left text-xs font-bold uppercase text-slate-500">Колір</th>
                                    <th class="w-28 border-b border-slate-100 pb-3 pr-3 text-left text-xs font-bold uppercase text-slate-500">Порядок</th>
                                    <th class="w-12 border-b border-slate-100 pb-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(value, index) in form.values" :key="value.id ?? `new-${index}`">
                                    <td class="pr-3 pt-3 align-top">
                                        <input
                                            v-model="value.value"
                                            type="text"
                                            class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                            placeholder="Напр., Штучне хутро"
                                        />
                                        <InputError class="mt-2" :message="form.errors[`values.${index}.value`]" />
                                    </td>
                                    <td class="pr-3 pt-3 align-top">
                                        <div class="flex gap-2">
                                            <input
                                                v-model="value.slug"
                                                type="text"
                                                class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                                placeholder="shtuchne-hutro"
                                            />
                                            <button
                                                type="button"
                                                class="shrink-0 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                                @click="generateValueSlug(index)"
                                            >
                                                Slug
                                            </button>
                                        </div>
                                        <InputError class="mt-2" :message="form.errors[`values.${index}.slug`]" />
                                    </td>
                                    <td v-if="isColorType" class="pr-3 pt-3 align-top">
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model="value.color_hex"
                                                type="text"
                                                class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                                placeholder="#7561f7"
                                            />
                                            <span
                                                class="h-10 w-10 shrink-0 rounded-lg border border-slate-200"
                                                :style="{ backgroundColor: value.color_hex || '#ffffff' }"
                                            />
                                        </div>
                                        <InputError class="mt-2" :message="form.errors[`values.${index}.color_hex`]" />
                                    </td>
                                    <td class="pr-3 pt-3 align-top">
                                        <input
                                            v-model="value.sort_order"
                                            type="number"
                                            min="0"
                                            class="w-24 rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                        />
                                    </td>
                                    <td class="pt-3 align-top">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-red-300 hover:text-red-600"
                                            aria-label="Видалити значення"
                                            @click="removeValue(index)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <InputError class="mt-3" :message="form.errors.values" />
                </div>
            </section>

            <aside class="space-y-5">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Публікація</h2>

                    <label class="mt-5 flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                        <span>
                            <span class="block text-sm font-bold text-slate-700">Показувати у фільтрах</span>
                            <span class="block text-xs font-medium text-slate-500">Storefront зможе будувати URL</span>
                        </span>
                        <input
                            v-model="form.is_filterable"
                            type="checkbox"
                            class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                        />
                    </label>

                    <label class="mt-3 flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                        <span>
                            <span class="block text-sm font-bold text-slate-700">Опція варіанта</span>
                            <span class="block text-xs font-medium text-slate-500">Розмір, колір або інша SKU-опція</span>
                        </span>
                        <input
                            v-model="form.is_variant_option"
                            type="checkbox"
                            class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                        />
                    </label>

                    <button
                        type="submit"
                        class="mt-5 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white transition hover:bg-[#6552e8] disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Save class="h-4 w-4" />
                        Зберегти
                    </button>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex items-center gap-2">
                        <Link2 class="h-5 w-5 text-[#7561f7]" />
                        <h2 class="text-lg font-bold text-[#343241]">SEO URL фільтра</h2>
                    </div>

                    <p class="mt-3 text-sm leading-6 text-slate-500">
                        У storefront вибрані фільтри формуватимуть canonical path. Наприклад:
                    </p>

                    <code class="mt-4 block break-words rounded-lg bg-slate-100 px-3 py-3 text-xs font-bold leading-6 text-slate-700">
                        {{ previewUrl || filterExampleUrl }}
                    </code>

                    <p class="mt-3 text-xs font-semibold leading-5 text-slate-500">
                        Розділювач `--` не конфліктує зі звичайними дефісами в slug і дає стабільний parse для SEO.
                    </p>
                </section>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
