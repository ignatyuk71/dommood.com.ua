<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    FolderTree,
    Image,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const success = computed(() => page.props.flash?.success);
const formError = computed(() => page.props.errors?.category);
const search = ref(props.filters.search ?? '');

const applySearch = () => {
    router.get(route('admin.categories.index'), {
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const destroyCategory = (category) => {
    if (!window.confirm(`Видалити категорію "${category.name}"?`)) {
        return;
    }

    router.delete(route('admin.categories.destroy', category.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Категорії" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">Категорії</h1>
                </div>

                <Link
                    :href="route('admin.categories.create')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_12px_28px_rgba(117,97,247,0.28)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати категорію
                </Link>
            </div>
        </template>

        <div v-if="success" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ success }}
        </div>
        <div v-if="formError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            {{ formError }}
        </div>

        <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="border-b border-slate-100 px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-[#343241]">Структура каталогу</h2>
                        <p class="text-sm text-slate-500">Категорії формують SEO-сторінки, меню і фільтрацію товарів.</p>
                    </div>

                    <form class="flex w-full max-w-xl gap-2" @submit.prevent="applySearch">
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="w-full rounded-lg border-slate-200 pl-9 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Пошук за назвою, slug або SEO title"
                            />
                        </div>
                        <button
                            type="submit"
                            class="rounded-lg bg-[#343241] px-4 text-sm font-bold text-white transition hover:bg-[#292736]"
                        >
                            Знайти
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/70">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Категорія</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Батьківська</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Товари</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Статус</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">SEO</th>
                            <th class="px-5 py-4 text-right text-xs font-bold uppercase text-slate-500">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="category in categories.data" :key="category.id" class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#f5f4ff] text-[#7561f7]">
                                        <Image v-if="category.image_path" class="h-5 w-5" />
                                        <FolderTree v-else class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-[#343241]">{{ category.name }}</div>
                                        <code class="mt-1 inline-block rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">
                                            /{{ category.slug }}
                                        </code>
                                        <div v-if="category.description" class="mt-2 max-w-xl text-sm leading-6 text-slate-500">
                                            {{ category.description }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div v-if="category.parent" class="text-sm font-bold text-[#343241]">{{ category.parent.name }}</div>
                                <span v-else class="text-sm font-semibold text-slate-400">Коренева</span>
                                <div v-if="category.children_count" class="mt-1 text-xs font-semibold text-slate-500">
                                    Дочірніх: {{ category.children_count }}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-slate-600">
                                <div>У категорії: {{ category.products_count }}</div>
                                <div class="mt-1 text-xs text-slate-500">Основна: {{ category.primary_products_count }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold"
                                    :class="category.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ category.is_active ? 'Активна' : 'Вимкнена' }}
                                </span>
                                <div class="mt-2 text-xs font-semibold text-slate-500">Порядок: {{ category.sort_order }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="max-w-xs text-sm font-semibold text-[#343241]">{{ category.meta_title || '—' }}</div>
                                <div v-if="category.meta_description" class="mt-1 max-w-xs text-xs leading-5 text-slate-500">
                                    {{ category.meta_description }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="route('admin.categories.edit', category.id)"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                        aria-label="Редагувати"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-red-300 hover:text-red-600"
                                        aria-label="Видалити"
                                        @click="destroyCategory(category)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="categories.data.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-sm font-semibold text-slate-500">
                                Категорій ще немає.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="categories.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                <template v-for="link in categories.links" :key="link.label">
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
