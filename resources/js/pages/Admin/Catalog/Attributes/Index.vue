<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Filter,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Trash2,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    attributes: {
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
    router.get(route('admin.attributes.index'), {
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const destroyAttribute = (attribute) => {
    if (!window.confirm(`Видалити характеристику "${attribute.name}"?`)) {
        return;
    }

    router.delete(route('admin.attributes.destroy', attribute.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Характеристики" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-[#343241]">Характеристики</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Каталог</p>
                </div>

                <Link
                    :href="route('admin.attributes.create')"
                    class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_10px_22px_rgba(117,97,247,0.24)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати характеристику
                </Link>
            </div>
        </template>

        <section class="space-y-2">
            <div class="rounded-lg border border-slate-200 bg-white px-3 py-3 shadow-[0_8px_22px_rgba(15,23,42,0.045)]">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-[#343241]">Фільтри каталогу</h2>
                        <p class="text-xs font-semibold text-slate-500">Фільтри, варіанти товарів і SEO URL сторінок видачі.</p>
                    </div>

                    <form class="flex w-full max-w-xl gap-2" @submit.prevent="applySearch">
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="h-9 w-full rounded-md border-slate-200 pl-9 text-sm font-semibold shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Пошук за назвою, slug або значенням"
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

            <div class="hidden rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 xl:grid xl:grid-cols-[minmax(260px,1fr)_minmax(360px,1.4fr)_120px_170px_88px] xl:gap-4">
                <div>Характеристика</div>
                <div>Значення</div>
                <div>Тип</div>
                <div>Фільтри</div>
                <div class="text-right">Дії</div>
            </div>

            <div v-if="attributes.data.length" class="space-y-2">
                <article
                    v-for="attribute in attributes.data"
                    :key="attribute.id"
                    class="grid items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-[0_8px_22px_rgba(15,23,42,0.045)] transition hover:border-slate-300 hover:shadow-[0_12px_28px_rgba(15,23,42,0.07)] xl:grid-cols-[minmax(260px,1fr)_minmax(360px,1.4fr)_120px_170px_88px] xl:gap-4"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-[#f5f4ff] text-[#7561f7] ring-1 ring-slate-200">
                            <SlidersHorizontal class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <Link
                                :href="route('admin.attributes.edit', attribute.id)"
                                class="block truncate text-sm font-bold text-[#242231] transition hover:text-[#7561f7] hover:underline"
                            >
                                {{ attribute.name }}
                            </Link>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ attribute.slug }}</code>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-500">№{{ attribute.sort_order }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex max-h-14 min-w-0 flex-wrap gap-1 overflow-hidden">
                        <span
                            v-for="value in attribute.values.slice(0, 10)"
                            :key="value.id"
                            class="inline-flex items-center gap-1.5 rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600"
                        >
                            <span
                                v-if="value.color_hex"
                                class="h-3 w-3 rounded-full ring-1 ring-slate-200"
                                :style="{ backgroundColor: value.color_hex }"
                            />
                            {{ value.value }}
                        </span>
                        <span
                            v-if="attribute.values_count > 10"
                            class="rounded bg-[#f5f4ff] px-2 py-0.5 text-[11px] font-bold text-[#7561f7]"
                        >
                            +{{ attribute.values_count - 10 }}
                        </span>
                        <span v-if="attribute.values_count === 0" class="text-sm font-semibold text-slate-400">
                            Значень ще немає
                        </span>
                    </div>

                    <div>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">
                            {{ attribute.type_label }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-1">
                        <span
                            class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-bold"
                            :class="attribute.is_filterable ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        >
                            <Filter class="h-3 w-3" />
                            {{ attribute.is_filterable ? 'У фільтрах' : 'Без фільтра' }}
                        </span>
                        <span
                            v-if="attribute.is_variant_option"
                            class="rounded bg-[#f5f4ff] px-2 py-0.5 text-[11px] font-bold text-[#7561f7]"
                        >
                            Варіант
                        </span>
                    </div>

                    <div class="flex justify-end gap-1.5">
                        <Link
                            :href="route('admin.attributes.edit', attribute.id)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-[#7561f7] hover:text-[#7561f7]"
                            aria-label="Редагувати"
                        >
                            <Pencil class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-red-300 hover:text-red-600"
                            aria-label="Видалити"
                            @click="destroyAttribute(attribute)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm font-semibold text-slate-500">
                Характеристик ще немає.
            </div>

            <div v-if="attributes.links?.length > 3" class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3">
                <template v-for="link in attributes.links" :key="link.label">
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
