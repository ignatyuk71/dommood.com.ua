<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    FolderTree,
    Image,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';

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
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-[#343241]">Категорії</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Каталог</p>
                </div>

                <Link
                    :href="route('admin.categories.create')"
                    class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_10px_22px_rgba(117,97,247,0.24)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати категорію
                </Link>
            </div>
        </template>

        <section class="space-y-2">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-3 shadow-[0_8px_22px_rgba(15,23,42,0.045)]">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-[#343241]">Структура каталогу</h2>
                        <p class="text-xs font-semibold text-slate-500">SEO-сторінки, меню і фільтрація товарів.</p>
                    </div>

                    <form class="flex w-full max-w-xl gap-2" @submit.prevent="applySearch">
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="h-9 w-full rounded-md border-slate-200 pl-9 text-sm font-semibold shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Пошук за назвою, slug або SEO title"
                            />
                        </div>
                        <button
                            type="submit"
                            class="rounded-md bg-[#343241] px-4 text-sm font-bold text-white transition hover:bg-[#292736]"
                        >
                            Знайти
                        </button>
                    </form>
                </div>
            </div>

            <div class="hidden rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 xl:grid xl:grid-cols-[minmax(360px,1fr)_180px_150px_130px_minmax(220px,300px)_88px] xl:gap-4">
                <div>Категорія</div>
                <div>Батьківська</div>
                <div>Товари</div>
                <div>Статус</div>
                <div>SEO</div>
                <div class="text-right">Дії</div>
            </div>

            <div v-if="categories.data.length" class="space-y-2">
                <article
                    v-for="category in categories.data"
                    :key="category.id"
                    class="grid items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-[0_8px_22px_rgba(15,23,42,0.045)] transition hover:border-slate-300 hover:shadow-[0_12px_28px_rgba(15,23,42,0.07)] xl:grid-cols-[minmax(360px,1fr)_180px_150px_130px_minmax(220px,300px)_88px] xl:gap-4"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-md bg-[#f5f4ff] text-[#7561f7] ring-1 ring-slate-200">
                            <img
                                v-if="category.image_url"
                                :src="category.image_url"
                                :alt="category.name"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <Image v-if="category.image_path" class="h-5 w-5" />
                                <FolderTree v-else class="h-5 w-5" />
                            </div>
                            <span class="absolute left-1 top-1 rounded bg-white/90 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-600 shadow-sm">
                                #{{ category.id }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <Link
                                :href="route('admin.categories.edit', category.id)"
                                class="block truncate text-sm font-bold text-[#242231] transition hover:text-[#7561f7] hover:underline"
                            >
                                {{ category.name }}
                            </Link>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">/{{ category.slug }}</code>
                                <span v-if="category.children_count" class="rounded bg-[#eef2ff] px-1.5 py-0.5 text-[11px] font-bold text-[#4451b8]">
                                    {{ category.children_count }} доч.
                                </span>
                            </div>
                            <p v-if="category.description" class="mt-1 max-h-8 overflow-hidden text-xs font-semibold leading-4 text-slate-500">
                                {{ category.description }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <div v-if="category.parent" class="truncate text-sm font-bold text-[#343241]">{{ category.parent.name }}</div>
                        <span v-else class="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">Коренева</span>
                    </div>

                    <div class="text-xs font-semibold text-slate-600">
                        <div class="font-bold text-[#343241]">{{ category.products_count }} у категорії</div>
                        <div class="mt-0.5 text-slate-500">Основна: {{ category.primary_products_count }}</div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded px-2 py-0.5 text-[11px] font-bold"
                            :class="category.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ category.is_active ? 'Активна' : 'Вимкнена' }}
                        </span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">№{{ category.sort_order }}</span>
                    </div>

                    <div class="min-w-0">
                        <div class="truncate text-xs font-bold text-[#343241]">{{ category.meta_title || '—' }}</div>
                        <div v-if="category.meta_description" class="mt-0.5 max-h-8 overflow-hidden text-[11px] font-semibold leading-4 text-slate-500">
                            {{ category.meta_description }}
                        </div>
                    </div>

                    <div class="flex justify-end gap-1.5">
                        <Link
                            :href="route('admin.categories.edit', category.id)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-[#7561f7] hover:text-[#7561f7]"
                            aria-label="Редагувати"
                        >
                            <Pencil class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-red-300 hover:text-red-600"
                            aria-label="Видалити"
                            @click="destroyCategory(category)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm font-semibold text-slate-500">
                Категорій ще немає.
            </div>

            <div v-if="categories.links?.length > 3" class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3">
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
