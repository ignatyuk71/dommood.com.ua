<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Layers3, Pencil, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    banners: {
        type: Object,
        required: true,
    },
    placementOptions: {
        type: Array,
        required: true,
    },
});

const emptyForm = () => ({
    title: '',
    placement: 'home_hero_main',
    url: '/catalog',
    button_text: '',
    image: null,
    mobile_image: null,
    delete_image: false,
    delete_mobile_image: false,
    is_active: true,
    sort_order: 0,
    starts_at: '',
    ends_at: '',
});

const editingBanner = ref(null);
const imageInput = ref(null);
const mobileImageInput = ref(null);
const imagePreview = ref(null);
const mobileImagePreview = ref(null);
const imageName = ref('');
const mobileImageName = ref('');
let imageObjectUrl = null;
let mobileImageObjectUrl = null;

const form = useForm(emptyForm());
const isEditing = computed(() => Boolean(editingBanner.value));
const submitLabel = computed(() => (isEditing.value ? 'Оновити банер' : 'Створити банер'));
const selectedPlacement = computed(() => props.placementOptions.find((placement) => placement.value === form.placement) ?? props.placementOptions[0] ?? {});
const desktopRecommendedSize = computed(() => selectedPlacement.value.desktop_size ?? '2400×1200 px');
const mobileRecommendedSize = computed(() => selectedPlacement.value.mobile_size ?? '1080×1200 px');
const desktopRecommendationNote = computed(() => selectedPlacement.value.desktop_note ?? '');
const mobileRecommendationNote = computed(() => selectedPlacement.value.mobile_note ?? '');
const desktopUploadHint = computed(() => [
    `Рекомендовано ${desktopRecommendedSize.value}.`,
    desktopRecommendationNote.value,
    'JPG, PNG або WebP до 6 MB. Збережеться як WebP.',
].filter(Boolean).join(' '));
const mobileUploadHint = computed(() => [
    `Рекомендовано ${mobileRecommendedSize.value}.`,
    mobileRecommendationNote.value,
    'Якщо порожньо, буде desktop фото. Збережеться як WebP.',
].filter(Boolean).join(' '));

const revokeObjectUrl = (type = null) => {
    if ((!type || type === 'image') && imageObjectUrl) {
        URL.revokeObjectURL(imageObjectUrl);
        imageObjectUrl = null;
    }

    if ((!type || type === 'mobile') && mobileImageObjectUrl) {
        URL.revokeObjectURL(mobileImageObjectUrl);
        mobileImageObjectUrl = null;
    }
};

const resetForm = () => {
    revokeObjectUrl();
    editingBanner.value = null;
    imagePreview.value = null;
    mobileImagePreview.value = null;
    imageName.value = '';
    mobileImageName.value = '';
    form.defaults(emptyForm());
    form.reset();
    form.clearErrors();
};

const editBanner = (banner) => {
    revokeObjectUrl();
    editingBanner.value = banner;
    imagePreview.value = banner.image_url;
    mobileImagePreview.value = banner.mobile_image_url;
    imageName.value = banner.image_path || '';
    mobileImageName.value = banner.mobile_image_path || '';

    form.defaults({
        title: banner.title ?? '',
        placement: banner.placement ?? 'home_hero_main',
        url: banner.url ?? '',
        button_text: banner.button_text ?? '',
        image: null,
        mobile_image: null,
        delete_image: false,
        delete_mobile_image: false,
        is_active: banner.is_active ?? true,
        sort_order: banner.sort_order ?? 0,
        starts_at: banner.starts_at ?? '',
        ends_at: banner.ends_at ?? '',
    });
    form.reset();
    form.clearErrors();
};

const setImageFile = (file, type = 'image') => {
    if (!file) {
        return;
    }

    if (type === 'mobile') {
        revokeObjectUrl('mobile');
        form.mobile_image = file;
        form.delete_mobile_image = false;
        mobileImageName.value = file.name;
        mobileImageObjectUrl = URL.createObjectURL(file);
        mobileImagePreview.value = mobileImageObjectUrl;
        return;
    }

    revokeObjectUrl('image');
    form.image = file;
    form.delete_image = false;
    imageName.value = file.name;
    imageObjectUrl = URL.createObjectURL(file);
    imagePreview.value = imageObjectUrl;
};

const clearImage = (type = 'image') => {
    if (type === 'mobile') {
        revokeObjectUrl('mobile');
        form.mobile_image = null;
        form.delete_mobile_image = Boolean(mobileImagePreview.value || mobileImageName.value);
        mobileImagePreview.value = null;
        mobileImageName.value = '';
        return;
    }

    revokeObjectUrl('image');
    form.image = null;
    form.delete_image = Boolean(imagePreview.value || imageName.value);
    imagePreview.value = null;
    imageName.value = '';
};

const submit = () => {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: resetForm,
    };

    if (editingBanner.value) {
        form
            .transform((data) => ({
                ...data,
                _method: 'put',
                delete_image: data.delete_image ? 1 : 0,
                delete_mobile_image: data.delete_mobile_image ? 1 : 0,
                is_active: data.is_active ? 1 : 0,
            }))
            .post(route('admin.banners.update', editingBanner.value.id), options);
        return;
    }

    form
        .transform((data) => ({
            ...data,
            delete_image: data.delete_image ? 1 : 0,
            delete_mobile_image: data.delete_mobile_image ? 1 : 0,
            is_active: data.is_active ? 1 : 0,
        }))
        .post(route('admin.banners.store'), options);
};

const destroyBanner = (banner) => {
    if (!window.confirm(`Видалити банер "${banner.title}"?`)) {
        return;
    }

    router.delete(route('admin.banners.destroy', banner.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingBanner.value?.id === banner.id) {
                resetForm();
            }
        },
    });
};

onBeforeUnmount(() => {
    revokeObjectUrl();
});
</script>

<template>
    <Head title="Банери" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Контент</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">Банери</h1>
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_12px_28px_rgba(117,97,247,0.28)] transition hover:bg-[#6552e8]"
                    @click="resetForm"
                >
                    <Plus class="h-4 w-4" />
                    Новий банер
                </button>
            </div>
        </template>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
            <section class="space-y-3">
                <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white px-4 py-4 shadow-[0_8px_22px_rgba(15,23,42,0.045)] md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-[#343241]">Список банерів</h2>
                        <p class="text-xs font-semibold text-slate-500">На головній використовується 3 слоти: великий, правий верхній і правий нижній.</p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-md bg-[#f5f4ff] px-2.5 py-1.5 text-xs font-bold text-[#7561f7]">
                        <Layers3 class="h-4 w-4" />
                        {{ banners.total }} записів
                    </div>
                </div>

                <div v-if="banners.data.length" class="space-y-2">
                    <article
                        v-for="banner in banners.data"
                        :key="banner.id"
                        class="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-[0_8px_22px_rgba(15,23,42,0.045)] xl:grid-cols-[160px_minmax(0,1fr)_96px]"
                    >
                        <div class="h-24 overflow-hidden rounded-md bg-slate-100 ring-1 ring-slate-200">
                            <img v-if="banner.image_url" :src="banner.image_url" :alt="banner.title" class="h-full w-full object-cover" loading="lazy" />
                            <div v-else class="flex h-full items-center justify-center text-xs font-bold text-slate-400">Без фото</div>
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-sm font-bold text-[#343241]">{{ banner.title }}</h3>
                                <span
                                    class="rounded px-2 py-0.5 text-[11px] font-bold"
                                    :class="banner.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ banner.is_active ? 'Активний' : 'Вимкнений' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ banner.placement_label }} · №{{ banner.sort_order }}</p>
                            <p v-if="banner.url" class="mt-2 truncate text-xs font-semibold text-slate-500">{{ banner.url }}</p>
                            <div v-if="banner.starts_at || banner.ends_at" class="mt-2 text-xs font-semibold text-slate-400">
                                {{ banner.starts_at || 'без старту' }} - {{ banner.ends_at || 'без завершення' }}
                            </div>
                        </div>

                        <div class="flex items-start justify-end gap-1.5">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                aria-label="Редагувати"
                                @click="editBanner(banner)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-red-300 hover:text-red-600"
                                aria-label="Видалити"
                                @click="destroyBanner(banner)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm font-semibold text-slate-500">
                    Банерів ще немає.
                </div>
            </section>

            <form class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submit">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-[#343241]">{{ isEditing ? 'Редагування банера' : 'Новий банер' }}</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">3 блоки на головній: 1 великий і 2 малі. Файли автоматично конвертуються у WebP.</p>
                    </div>
                    <button v-if="isEditing" type="button" class="text-slate-400 transition hover:text-slate-700" aria-label="Скасувати редагування" @click="resetForm">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 p-5">
                    <div>
                        <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500" for="banner_title">Назва</label>
                        <input id="banner_title" v-model="form.title" type="text" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" required />
                        <InputError class="mt-1" :message="form.errors.title" />
                    </div>

                    <div>
                        <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500" for="banner_placement">Позиція</label>
                        <select id="banner_placement" v-model="form.placement" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]">
                            <option v-for="placement in placementOptions" :key="placement.value" :value="placement.value">{{ placement.label }}</option>
                        </select>
                        <div class="mt-2 rounded-md border border-[#ded8ff] bg-[#f8f7ff] px-3 py-2 text-xs font-bold text-[#343241]">
                            <div>Desktop: {{ desktopRecommendedSize }}</div>
                            <p v-if="desktopRecommendationNote" class="mt-1 leading-5 text-slate-600">{{ desktopRecommendationNote }}</p>
                            <div class="mt-2">Mobile: {{ mobileRecommendedSize }}</div>
                            <p v-if="mobileRecommendationNote" class="mt-1 leading-5 text-slate-600">{{ mobileRecommendationNote }}</p>
                        </div>
                        <InputError class="mt-1" :message="form.errors.placement" />
                    </div>

                    <div>
                        <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500" for="banner_url">URL переходу</label>
                        <input id="banner_url" v-model="form.url" type="text" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="/catalog або https://..." />
                        <InputError class="mt-1" :message="form.errors.url" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <span class="text-sm font-extrabold text-[#343241]">Активний</span>
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                        </label>
                        <div>
                            <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500" for="banner_sort">Порядок</label>
                            <input id="banner_sort" v-model="form.sort_order" type="number" min="0" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                            <InputError class="mt-1" :message="form.errors.sort_order" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500" for="banner_starts">Початок</label>
                            <input id="banner_starts" v-model="form.starts_at" type="datetime-local" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                            <InputError class="mt-1" :message="form.errors.starts_at" />
                        </div>
                        <div>
                            <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500" for="banner_ends">Завершення</label>
                            <input id="banner_ends" v-model="form.ends_at" type="datetime-local" class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                            <InputError class="mt-1" :message="form.errors.ends_at" />
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Desktop фото</label>
                        <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="setImageFile($event.target.files?.[0], 'image'); $event.target.value = ''" />
                        <button type="button" class="mt-1 flex min-h-48 w-full items-center justify-center overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50 transition hover:border-[#7561f7] hover:bg-[#f5f4ff]" @click="imageInput?.click()" @dragover.prevent @drop.prevent="setImageFile($event.dataTransfer.files?.[0], 'image')">
                            <img v-if="imagePreview" :src="imagePreview" alt="" class="h-full max-h-64 w-full object-cover" />
                            <span v-else class="flex flex-col items-center gap-2 px-5 py-8 text-center">
                                <ImagePlus class="h-7 w-7 text-[#7561f7]" />
                                <span class="text-sm font-bold text-[#343241]">Завантажити банер</span>
                            </span>
                        </button>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="break-all text-xs font-semibold text-slate-500">{{ imageName || desktopUploadHint }}</span>
                            <button v-if="imagePreview || imageName" type="button" class="text-xs font-bold text-red-600" @click="clearImage('image')">Видалити</button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.image" />
                    </div>

                    <div>
                        <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Mobile фото</label>
                        <input ref="mobileImageInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="setImageFile($event.target.files?.[0], 'mobile'); $event.target.value = ''" />
                        <button type="button" class="mt-1 flex min-h-40 w-full items-center justify-center overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50 transition hover:border-[#7561f7] hover:bg-[#f5f4ff]" @click="mobileImageInput?.click()" @dragover.prevent @drop.prevent="setImageFile($event.dataTransfer.files?.[0], 'mobile')">
                            <img v-if="mobileImagePreview" :src="mobileImagePreview" alt="" class="h-full max-h-56 w-full object-cover" />
                            <span v-else class="flex flex-col items-center gap-2 px-5 py-8 text-center">
                                <ImagePlus class="h-7 w-7 text-[#7561f7]" />
                                <span class="text-sm font-bold text-[#343241]">Опційне mobile фото</span>
                            </span>
                        </button>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="break-all text-xs font-semibold text-slate-500">{{ mobileImageName || mobileUploadHint }}</span>
                            <button v-if="mobileImagePreview || mobileImageName" type="button" class="text-xs font-bold text-red-600" @click="clearImage('mobile')">Видалити</button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.mobile_image" />
                    </div>
                </div>

                <div class="border-t border-slate-100 px-5 py-4">
                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#343241] text-sm font-extrabold text-white transition hover:bg-[#24212f] disabled:opacity-60" :disabled="form.processing">
                        <Save class="h-5 w-5" />
                        {{ submitLabel }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
