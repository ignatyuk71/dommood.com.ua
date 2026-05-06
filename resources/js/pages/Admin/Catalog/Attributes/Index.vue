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
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">Характеристики</h1>
                </div>

                <Link
                    :href="route('admin.attributes.create')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_12px_28px_rgba(117,97,247,0.28)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати характеристику
                </Link>
            </div>
        </template>

        <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="border-b border-slate-100 px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-[#343241]">Фільтри каталогу</h2>
                        <p class="text-sm text-slate-500">
                            Характеристики формують фільтри, варіанти товарів і SEO URL сторінок видачі.
                        </p>
                    </div>

                    <form class="flex w-full max-w-xl gap-2" @submit.prevent="applySearch">
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="w-full rounded-lg border-slate-200 pl-9 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Пошук за назвою, slug або значенням"
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
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Характеристика</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Значення</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Тип</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Фільтри</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Порядок</th>
                            <th class="px-5 py-4 text-right text-xs font-bold uppercase text-slate-500">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="attribute in attributes.data" :key="attribute.id" class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[#f5f4ff] text-[#7561f7] ring-1 ring-slate-100">
                                        <SlidersHorizontal class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-[#343241]">{{ attribute.name }}</div>
                                        <code class="mt-1 inline-block rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">
                                            {{ attribute.slug }}
                                        </code>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex max-w-xl flex-wrap gap-2">
                                    <span
                                        v-for="value in attribute.values.slice(0, 8)"
                                        :key="value.id"
                                        class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"
                                    >
                                        <span
                                            v-if="value.color_hex"
                                            class="h-3 w-3 rounded-full ring-1 ring-slate-200"
                                            :style="{ backgroundColor: value.color_hex }"
                                        />
                                        {{ value.value }}
                                    </span>
                                    <span
                                        v-if="attribute.values_count > 8"
                                        class="rounded-full bg-[#f5f4ff] px-3 py-1 text-xs font-bold text-[#7561f7]"
                                    >
                                        +{{ attribute.values_count - 8 }}
                                    </span>
                                    <span v-if="attribute.values_count === 0" class="text-sm font-semibold text-slate-400">
                                        Значень ще немає
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                                    {{ attribute.type_label }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="space-y-2">
                                    <span
                                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold"
                                        :class="attribute.is_filterable ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                    >
                                        <Filter class="h-3.5 w-3.5" />
                                        {{ attribute.is_filterable ? 'У фільтрах' : 'Без фільтра' }}
                                    </span>
                                    <div
                                        v-if="attribute.is_variant_option"
                                        class="text-xs font-semibold text-[#7561f7]"
                                    >
                                        Опція варіанта товару
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-slate-600">{{ attribute.sort_order }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="route('admin.attributes.edit', attribute.id)"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                        aria-label="Редагувати"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-red-300 hover:text-red-600"
                                        aria-label="Видалити"
                                        @click="destroyAttribute(attribute)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="attributes.data.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-sm font-semibold text-slate-500">
                                Характеристик ще немає.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="attributes.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
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
