<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    group: {
        type: Object,
        required: true,
    },
});

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    name: props.group.name ?? '',
    code: props.group.code ?? '',
    description: props.group.description ?? '',
    is_active: props.group.is_active ?? true,
    sort_order: props.group.sort_order ?? 0,
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

const generateCode = () => {
    form.code = transliterate(form.name)
        .replace(/[^a-z0-9_.-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_{2,}/g, '_');
};

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.color-groups.update', props.group.id));
        return;
    }

    form.post(route('admin.color-groups.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Редагування групи кольорів' : 'Створення групи кольорів'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">
                        {{ isEdit ? 'Редагування групи кольорів' : 'Створення групи кольорів' }}
                    </h1>
                </div>

                <Link
                    :href="route('admin.color-groups.index')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                >
                    <ArrowLeft class="h-4 w-4" />
                    До списку
                </Link>
            </div>
        </template>

        <form class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="submit">
            <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <h2 class="text-lg font-bold text-[#343241]">Основне</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Група потрібна, щоб обʼєднати різні кольори одного товару в один блок на картці товару.
                </p>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-bold text-slate-700" for="name">Назва групи</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="Напр., Cozy home fleece"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <label class="text-sm font-bold text-slate-700" for="code">Код групи</label>
                        <div class="mt-2 flex gap-2">
                            <input
                                id="code"
                                v-model="form.code"
                                type="text"
                                class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="cozy_home_fleece"
                            />
                            <button
                                type="button"
                                class="shrink-0 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                @click="generateCode"
                            >
                                Код
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.code" />
                        <p class="mt-2 text-xs font-medium text-slate-500">Латиниця, цифри, `_`, `-`, `.`. Якщо лишити пустим, backend згенерує код.</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-bold text-slate-700" for="description">Опис</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="Внутрішня нотатка для менеджерів"
                        />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>
                </div>
            </section>

            <aside class="space-y-5">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Публікація</h2>

                    <label class="mt-5 flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                        <span>
                            <span class="block text-sm font-bold text-slate-700">Активна</span>
                            <span class="block text-xs font-medium text-slate-500">Доступна для вибору в товарі</span>
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
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
