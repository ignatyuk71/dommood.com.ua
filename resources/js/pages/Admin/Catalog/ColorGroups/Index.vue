<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Droplet, Plus, Pencil, Trash2 } from 'lucide-vue-next';

defineProps({
    groups: {
        type: Object,
        required: true,
    },
});

const destroyGroup = (group) => {
    if (!window.confirm(`Видалити групу кольорів "${group.name}"?`)) {
        return;
    }

    router.delete(route('admin.color-groups.destroy', group.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Групи кольорів" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-[#343241]">Групи кольорів</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Каталог</p>
                </div>

                <Link
                    :href="route('admin.color-groups.create')"
                    class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_10px_22px_rgba(117,97,247,0.24)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати групу
                </Link>
            </div>
        </template>

        <section class="space-y-2">
            <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white px-3 py-3 shadow-[0_8px_22px_rgba(15,23,42,0.045)] md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-[#343241]">Список груп</h2>
                    <p class="text-xs font-semibold text-slate-500">Групи обʼєднують товари одного дизайну в різних кольорах.</p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-md bg-[#f5f4ff] px-2.5 py-1.5 text-xs font-bold text-[#7561f7]">
                    <Droplet class="h-4 w-4" />
                    {{ groups.total }} записів
                </div>
            </div>

            <div class="hidden rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 xl:grid xl:grid-cols-[minmax(360px,1fr)_150px_120px_130px_90px] xl:gap-4">
                <div>Група</div>
                <div>Код</div>
                <div>Товари</div>
                <div>Статус</div>
                <div class="text-right">Дії</div>
            </div>

            <div v-if="groups.data.length" class="space-y-2">
                <article
                    v-for="group in groups.data"
                    :key="group.id"
                    class="grid items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-[0_8px_22px_rgba(15,23,42,0.045)] transition hover:border-slate-300 hover:shadow-[0_12px_28px_rgba(15,23,42,0.07)] xl:grid-cols-[minmax(360px,1fr)_150px_120px_130px_90px] xl:gap-4"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-[#f5f4ff] text-[#7561f7] ring-1 ring-slate-200">
                            <Droplet class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <Link
                                :href="route('admin.color-groups.edit', group.id)"
                                class="block truncate text-sm font-bold text-[#242231] transition hover:text-[#7561f7] hover:underline"
                            >
                                {{ group.name }}
                            </Link>
                            <p v-if="group.description" class="mt-0.5 max-h-8 overflow-hidden text-xs font-semibold leading-4 text-slate-500">
                                {{ group.description }}
                            </p>
                        </div>
                    </div>

                    <code class="w-fit rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ group.code }}</code>

                    <div class="text-sm font-bold text-[#343241]">{{ group.products_count }}</div>

                    <div class="flex flex-wrap gap-1">
                        <span
                            class="rounded px-2 py-0.5 text-[11px] font-bold"
                            :class="group.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ group.is_active ? 'Активна' : 'Вимкнена' }}
                        </span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">№{{ group.sort_order }}</span>
                    </div>

                    <div class="flex justify-end gap-1.5">
                        <Link
                            :href="route('admin.color-groups.edit', group.id)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-[#7561f7] hover:text-[#7561f7]"
                            aria-label="Редагувати"
                        >
                            <Pencil class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:border-red-300 hover:text-red-600"
                            aria-label="Видалити"
                            @click="destroyGroup(group)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm font-semibold text-slate-500">
                Груп кольорів ще немає.
            </div>

            <div v-if="groups.links?.length > 3" class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3">
                <template v-for="link in groups.links" :key="link.label">
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
