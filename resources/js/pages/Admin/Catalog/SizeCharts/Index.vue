<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Grid2X2, Image, Pencil, Plus, Trash2 } from 'lucide-vue-next';

defineProps({
    charts: {
        type: Object,
        required: true,
    },
});

const destroyChart = (chart) => {
    if (!window.confirm(`Видалити розмірну сітку "${chart.title}"?`)) {
        return;
    }

    router.delete(route('admin.size-charts.destroy', chart.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Розмірні сітки" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">Розмірні сітки</h1>
                </div>

                <Link
                    :href="route('admin.size-charts.create')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_12px_28px_rgba(117,97,247,0.28)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати сітку
                </Link>
            </div>
        </template>

        <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-[#343241]">Список сіток</h2>
                    <p class="text-sm text-slate-500">Сітка привʼязується до товару і показує покупцю таблицю розмірів.</p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg bg-[#f5f4ff] px-3 py-2 text-sm font-bold text-[#7561f7]">
                    <Grid2X2 class="h-4 w-4" />
                    {{ charts.total }} записів
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/70">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Назва</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Код</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Товарів</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Статус</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Порядок</th>
                            <th class="px-5 py-4 text-right text-xs font-bold uppercase text-slate-500">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="chart in charts.data" :key="chart.id" class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 inline-flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#f5f4ff] text-[#7561f7] ring-1 ring-slate-100">
                                        <img
                                            v-if="chart.image_url"
                                            :src="chart.image_url"
                                            :alt="chart.title"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                        <Image v-else-if="chart.image_path" class="h-5 w-5" />
                                        <Grid2X2 v-else class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-[#343241]">{{ chart.title }}</div>
                                        <div v-if="chart.description" class="mt-1 max-w-xl text-sm text-slate-500">{{ chart.description }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <code class="rounded bg-slate-100 px-2 py-1 text-sm font-semibold text-slate-700">{{ chart.code }}</code>
                            </td>
                            <td class="px-5 py-4 text-sm font-bold text-[#343241]">{{ chart.products_count }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold"
                                    :class="chart.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ chart.is_active ? 'Активна' : 'Вимкнена' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-slate-600">{{ chart.sort_order }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="route('admin.size-charts.edit', chart.id)"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                        aria-label="Редагувати"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-red-300 hover:text-red-600"
                                        aria-label="Видалити"
                                        @click="destroyChart(chart)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="charts.data.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-sm font-semibold text-slate-500">
                                Розмірних сіток ще немає.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="charts.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                <template v-for="link in charts.links" :key="link.label">
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
