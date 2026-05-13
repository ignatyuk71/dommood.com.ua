<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Edit, FileText, Plus, Search, Trash2 } from 'lucide-vue-next';
import { watch } from 'vue';

const props = defineProps({
    pages: {
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
});

const filterForm = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

let searchTimer = null;

watch(() => [filterForm.search, filterForm.status], () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.pages.index'), {
            search: filterForm.search || undefined,
            status: filterForm.status || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 250);
});

const destroyPage = (page) => {
    if (!window.confirm(`Видалити сторінку "${page.title}"?`)) {
        return;
    }

    router.delete(route('admin.pages.destroy', page.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Сторінки" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Контент</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#343241]">Сторінки</h1>
                </div>

                <Link
                    :href="route('admin.pages.create')"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_10px_24px_rgba(117,97,247,0.22)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Нова сторінка
                </Link>
            </div>
        </template>

        <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[minmax(0,1fr)_220px]">
                <label class="relative block">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        class="h-10 w-full rounded-lg border-slate-200 pl-9 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        placeholder="Пошук за назвою або URL"
                    />
                </label>

                <select
                    v-model="filterForm.status"
                    class="h-10 rounded-lg border-slate-200 py-1 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                >
                    <option value="">Усі статуси</option>
                    <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                        {{ status.label }}
                    </option>
                </select>
            </div>

            <div v-if="pages.data.length" class="divide-y divide-slate-100">
                <article
                    v-for="page in pages.data"
                    :key="page.id"
                    class="grid gap-3 px-4 py-4 md:grid-cols-[minmax(0,1fr)_160px_120px] md:items-center"
                >
                    <div class="min-w-0">
                        <div class="flex min-w-0 items-center gap-2">
                            <FileText class="h-4 w-4 shrink-0 text-slate-400" />
                            <Link :href="route('admin.pages.edit', page.id)" class="truncate text-sm font-bold text-[#343241] hover:text-[#7561f7]">
                                {{ page.title }}
                            </Link>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                            <a :href="page.url" target="_blank" rel="noopener" class="font-mono hover:text-[#7561f7]">/{{ page.slug }}</a>
                            <span v-if="page.updated_at">оновлено {{ page.updated_at }}</span>
                        </div>
                    </div>

                    <span
                        class="inline-flex w-fit items-center rounded-lg px-2.5 py-1 text-xs font-bold"
                        :class="page.status === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ page.status_label }}
                    </span>

                    <div class="flex items-center gap-2 md:justify-end">
                        <Link
                            :href="route('admin.pages.edit', page.id)"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                            aria-label="Редагувати"
                        >
                            <Edit class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-500 transition hover:bg-red-50"
                            aria-label="Видалити"
                            @click="destroyPage(page)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="flex min-h-64 flex-col items-center justify-center p-8 text-center">
                <FileText class="h-10 w-10 text-slate-300" />
                <h2 class="mt-3 text-base font-bold text-[#343241]">Сторінок ще немає</h2>
                <p class="mt-1 max-w-md text-sm font-medium text-slate-500">Створи службові сторінки для верхньої полоски, футера, SEO та юридичних посилань.</p>
            </div>

            <div v-if="pages.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                <Link
                    v-for="link in pages.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-lg border px-3 py-2 text-sm"
                    :class="[
                        link.active ? 'border-[#7561f7] bg-[#7561f7] text-white' : 'border-slate-200 text-slate-600',
                        !link.url ? 'pointer-events-none opacity-40' : 'hover:border-[#7561f7] hover:text-[#7561f7]',
                    ]"
                    v-html="link.label"
                />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
