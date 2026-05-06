<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    chart: {
        type: Object,
        required: true,
    },
});

const isEdit = computed(() => props.mode === 'edit');
const defaultContent = {
    columns: ['Розмір', 'Довжина стопи, см'],
    rows: [['', '']],
};

const form = useForm({
    title: props.chart.title ?? '',
    code: props.chart.code ?? '',
    description: props.chart.description ?? '',
    content_json: props.chart.content_json ?? defaultContent,
    content_html: props.chart.content_html ?? '',
    image_path: props.chart.image_path ?? '',
    is_active: props.chart.is_active ?? true,
    sort_order: props.chart.sort_order ?? 0,
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
    form.code = transliterate(form.title)
        .replace(/[^a-z0-9_.-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_{2,}/g, '_');
};

const normalizeRows = () => {
    const columnCount = form.content_json.columns.length;
    form.content_json.rows = form.content_json.rows.map((row) => {
        const next = [...row];
        while (next.length < columnCount) {
            next.push('');
        }

        return next.slice(0, columnCount);
    });
};

const addColumn = () => {
    form.content_json.columns.push('');
    normalizeRows();
};

const removeColumn = (index) => {
    if (form.content_json.columns.length <= 1) {
        return;
    }

    form.content_json.columns.splice(index, 1);
    form.content_json.rows = form.content_json.rows.map((row) => row.filter((_, columnIndex) => columnIndex !== index));
};

const addRow = () => {
    form.content_json.rows.push(form.content_json.columns.map(() => ''));
};

const removeRow = (index) => {
    if (form.content_json.rows.length <= 1) {
        return;
    }

    form.content_json.rows.splice(index, 1);
};

const submit = () => {
    normalizeRows();

    if (isEdit.value) {
        form.put(route('admin.size-charts.update', props.chart.id));
        return;
    }

    form.post(route('admin.size-charts.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Редагування розмірної сітки' : 'Створення розмірної сітки'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">
                        {{ isEdit ? 'Редагування розмірної сітки' : 'Створення розмірної сітки' }}
                    </h1>
                </div>

                <Link
                    :href="route('admin.size-charts.index')"
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
                    <p class="mt-1 text-sm text-slate-500">
                        Одна сітка може використовуватись у багатьох товарах: капці, піжами, халати або інші групи.
                    </p>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="title">Назва</label>
                            <input
                                id="title"
                                v-model="form.title"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Напр., Капці жіночі"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="code">Код</label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="slippers_women"
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
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-bold text-slate-700" for="description">Опис</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Внутрішня нотатка або коротке пояснення"
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-[#343241]">Таблиця розмірів</h2>
                            <p class="mt-1 text-sm text-slate-500">Заповнюй як звичайну таблицю. Порожні рядки backend не збереже.</p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                @click="addColumn"
                            >
                                <Plus class="h-4 w-4" />
                                Колонка
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                @click="addRow"
                            >
                                <Plus class="h-4 w-4" />
                                Рядок
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full border-separate border-spacing-0">
                            <thead>
                                <tr>
                                    <th
                                        v-for="(_, columnIndex) in form.content_json.columns"
                                        :key="`column-${columnIndex}`"
                                        class="min-w-36 border-b border-slate-100 pb-3 pr-3 text-left"
                                    >
                                        <div class="flex gap-2">
                                            <input
                                                v-model="form.content_json.columns[columnIndex]"
                                                type="text"
                                                class="w-full rounded-lg border-slate-200 text-sm font-bold shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                                :placeholder="`Колонка ${columnIndex + 1}`"
                                            />
                                            <button
                                                type="button"
                                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-red-300 hover:text-red-600 disabled:opacity-40"
                                                :disabled="form.content_json.columns.length <= 1"
                                                aria-label="Видалити колонку"
                                                @click="removeColumn(columnIndex)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </th>
                                    <th class="w-12 border-b border-slate-100 pb-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, rowIndex) in form.content_json.rows" :key="`row-${rowIndex}`">
                                    <td
                                        v-for="(_, columnIndex) in form.content_json.columns"
                                        :key="`cell-${rowIndex}-${columnIndex}`"
                                        class="min-w-36 pr-3 pt-3"
                                    >
                                        <input
                                            v-model="row[columnIndex]"
                                            type="text"
                                            class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                            placeholder="Значення"
                                        />
                                    </td>
                                    <td class="pt-3">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-red-300 hover:text-red-600 disabled:opacity-40"
                                            :disabled="form.content_json.rows.length <= 1"
                                            aria-label="Видалити рядок"
                                            @click="removeRow(rowIndex)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <InputError class="mt-3" :message="form.errors.content_json" />
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

                    <div class="mt-5">
                        <label class="text-sm font-bold text-slate-700" for="image_path">Зображення</label>
                        <input
                            id="image_path"
                            v-model="form.image_path"
                            type="text"
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="size-charts/slippers.png"
                        />
                        <InputError class="mt-2" :message="form.errors.image_path" />
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
