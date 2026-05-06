<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImagePlus, Save, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    category: {
        type: Object,
        required: true,
    },
    parentOptions: {
        type: Array,
        required: true,
    },
});

const isEdit = computed(() => props.mode === 'edit');
const fileInput = ref(null);
const imagePreview = ref(props.category.image_url ?? null);
const selectedImageName = ref('');
let objectUrl = null;

const form = useForm({
    parent_id: props.category.parent_id ?? '',
    name: props.category.name ?? '',
    slug: props.category.slug ?? '',
    description: props.category.description ?? '',
    image: null,
    delete_image: false,
    is_active: props.category.is_active ?? true,
    sort_order: props.category.sort_order ?? 0,
    meta_title: props.category.meta_title ?? '',
    meta_description: props.category.meta_description ?? '',
    seo_text: props.category.seo_text ?? '',
});

const hasImage = computed(() => Boolean(imagePreview.value));
const canClearImage = computed(() => Boolean(imagePreview.value || form.image || props.category.image_path));

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

const openImageDialog = () => {
    fileInput.value?.click();
};

const revokeObjectUrl = () => {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
};

const setImageFile = (file) => {
    if (!file) {
        return;
    }

    revokeObjectUrl();
    form.image = file;
    form.delete_image = false;
    selectedImageName.value = file.name;
    objectUrl = URL.createObjectURL(file);
    imagePreview.value = objectUrl;
};

const onImageSelected = (event) => {
    setImageFile(event.target.files?.[0]);
    event.target.value = '';
};

const onImageDrop = (event) => {
    setImageFile(event.dataTransfer.files?.[0]);
};

const clearImage = () => {
    revokeObjectUrl();
    form.image = null;
    form.delete_image = Boolean(props.category.image_path || props.category.image_url);
    imagePreview.value = null;
    selectedImageName.value = '';
};

const submit = () => {
    if (isEdit.value) {
        form
            .transform((data) => ({
                ...data,
                _method: 'put',
                delete_image: data.delete_image ? 1 : 0,
            }))
            .post(route('admin.categories.update', props.category.id), {
                forceFormData: true,
            });
        return;
    }

    form
        .transform((data) => ({
            ...data,
            delete_image: data.delete_image ? 1 : 0,
        }))
        .post(route('admin.categories.store'), {
            forceFormData: true,
        });
};

onBeforeUnmount(() => {
    revokeObjectUrl();
});
</script>

<template>
    <Head :title="isEdit ? 'Редагування категорії' : 'Створення категорії'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">
                        {{ isEdit ? 'Редагування категорії' : 'Створення категорії' }}
                    </h1>
                </div>

                <Link
                    :href="route('admin.categories.index')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                >
                    <ArrowLeft class="h-4 w-4" />
                    До списку
                </Link>
            </div>
        </template>

        <form class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="submit">
            <section class="space-y-5">
                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Основне</h2>
                    <p class="mt-1 text-sm text-slate-500">Категорія буде використовуватись у меню, фільтрах і SEO-сторінках каталогу.</p>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="name">Назва</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Напр., Жіночі капці"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="relative">
                            <label class="text-sm font-bold text-slate-700" for="slug">Slug</label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="slug"
                                    v-model="form.slug"
                                    type="text"
                                    class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="zhinochi-kaptsi"
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
                            <p class="mt-2 text-xs font-medium text-slate-500">Латиниця, цифри й дефіси. Якщо пусто, backend згенерує slug.</p>
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="parent_id">Батьківська категорія</label>
                            <select
                                id="parent_id"
                                v-model="form.parent_id"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option value="">Коренева категорія</option>
                                <option v-for="parent in parentOptions" :key="parent.id" :value="parent.id">
                                    {{ parent.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.parent_id" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-bold text-slate-700" for="description">Опис</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="5"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Короткий опис категорії для верхнього блоку або адмінки"
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">SEO</h2>
                    <p class="mt-1 text-sm text-slate-500">Ці поля будуть основою для title, description і текстового SEO-блоку категорії.</p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="meta_title">Meta title</label>
                            <input
                                id="meta_title"
                                v-model="form.meta_title"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Жіночі капці купити в Україні | DomMood"
                            />
                            <InputError class="mt-2" :message="form.errors.meta_title" />
                        </div>

                        <div>
                            <label class="flex items-center justify-between gap-3 text-sm font-bold text-slate-700" for="meta_description">
                                <span>Meta description</span>
                                <span class="text-xs font-semibold text-slate-400">{{ form.meta_description.length }}/320</span>
                            </label>
                            <textarea
                                id="meta_description"
                                v-model="form.meta_description"
                                rows="3"
                                maxlength="320"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            />
                            <InputError class="mt-2" :message="form.errors.meta_description" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="seo_text">SEO текст</label>
                            <textarea
                                id="seo_text"
                                v-model="form.seo_text"
                                rows="8"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Текст для нижнього SEO-блоку категорії"
                            />
                            <InputError class="mt-2" :message="form.errors.seo_text" />
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-5">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Публікація</h2>

                    <label class="mt-5 flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                        <span>
                            <span class="block text-sm font-bold text-slate-700">Активна</span>
                            <span class="block text-xs font-medium text-slate-500">Показувати у storefront і меню</span>
                        </span>
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                        />
                    </label>

                    <div class="mt-5">
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
                    <h2 class="text-lg font-bold text-[#343241]">Зображення</h2>
                    <p class="mt-1 text-sm text-slate-500">Головне фото категорії для каталогу, меню й SEO-превʼю.</p>

                    <input
                        id="category_image"
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="onImageSelected"
                    />

                    <div class="relative mt-5">
                        <button
                            type="button"
                            class="flex min-h-52 w-full items-center justify-center overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50 text-left transition hover:border-[#7561f7] hover:bg-[#f5f4ff] focus:outline-none focus:ring-2 focus:ring-[#7561f7] focus:ring-offset-2"
                            @click="openImageDialog"
                            @dragover.prevent
                            @drop.prevent="onImageDrop"
                        >
                            <img
                                v-if="hasImage"
                                :src="imagePreview"
                                alt=""
                                class="h-full max-h-72 w-full object-cover"
                            />
                            <div v-else class="flex flex-col items-center gap-3 px-6 py-8 text-center">
                                <span class="inline-flex h-14 w-14 items-center justify-center rounded-lg bg-white text-[#7561f7] shadow-sm">
                                    <ImagePlus class="h-7 w-7" />
                                </span>
                                <span class="text-sm font-bold text-[#343241]">Натисни, щоб завантажити</span>
                                <span class="text-xs font-semibold text-slate-500">JPG, PNG або WebP до 4 MB</span>
                            </div>
                        </button>

                        <button
                            v-if="canClearImage"
                            type="button"
                            class="absolute right-3 top-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-100 bg-white text-red-600 shadow-[0_12px_28px_rgba(15,23,42,0.18)] transition hover:bg-red-50"
                            aria-label="Видалити зображення"
                            title="Видалити зображення"
                            @click.stop="clearImage"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div v-if="selectedImageName || category.image_path" class="mt-3 break-words text-xs font-semibold text-slate-500">
                        {{ selectedImageName || category.image_path }}
                    </div>
                    <InputError class="mt-2" :message="form.errors.image" />
                </section>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
