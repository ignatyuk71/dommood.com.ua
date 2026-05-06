<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    MessageSquare,
    Pencil,
    Plus,
    Search,
    Star,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    reviews: {
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
    statusCounts: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const success = computed(() => page.props.flash?.success);
const search = ref(props.filters.search ?? '');

const statusTabs = computed(() => [
    { value: '', label: 'Усі', count: props.statusCounts.all ?? 0 },
    ...props.statusOptions.map((status) => ({
        ...status,
        count: props.statusCounts[status.value] ?? 0,
    })),
]);

const statusClass = (status) => ({
    pending: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const applyFilter = (status = props.filters.status ?? '') => {
    router.get(route('admin.reviews.index'), {
        status: status || undefined,
        search: search.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const approveReview = (review) => {
    router.patch(route('admin.reviews.approve', review.id), {}, {
        preserveScroll: true,
    });
};

const rejectReview = (review) => {
    if (!window.confirm(`Відхилити відгук від "${review.author_name}"?`)) {
        return;
    }

    router.patch(route('admin.reviews.reject', review.id), {}, {
        preserveScroll: true,
    });
};

const destroyReview = (review) => {
    if (!window.confirm(`Видалити відгук від "${review.author_name}"?`)) {
        return;
    }

    router.delete(route('admin.reviews.destroy', review.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Відгуки" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">Відгуки</h1>
                </div>

                <Link
                    :href="route('admin.reviews.create')"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_12px_28px_rgba(117,97,247,0.28)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати відгук
                </Link>
            </div>
        </template>

        <div v-if="success" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ success }}
        </div>

        <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="border-b border-slate-100 px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-[#343241]">Модерація відгуків</h2>
                        <p class="text-sm text-slate-500">На storefront показуємо тільки відгуки зі статусом “Одобрено”.</p>
                    </div>

                    <form class="flex w-full max-w-xl gap-2" @submit.prevent="applyFilter()">
                        <div class="relative flex-1">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="search"
                                type="search"
                                class="w-full rounded-lg border-slate-200 pl-9 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Пошук за автором, товаром або текстом"
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

                <div class="mt-5 flex flex-wrap gap-2">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value || 'all'"
                        type="button"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-bold transition"
                        :class="(filters.status || '') === tab.value
                            ? 'bg-[#7561f7] text-white shadow-[0_10px_22px_rgba(117,97,247,0.24)]'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="applyFilter(tab.value)"
                    >
                        {{ tab.label }}
                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ tab.count }}</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50/70">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Відгук</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Товар</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Рейтинг</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Статус</th>
                            <th class="px-5 py-4 text-left text-xs font-bold uppercase text-slate-500">Дата</th>
                            <th class="px-5 py-4 text-right text-xs font-bold uppercase text-slate-500">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="review in reviews.data" :key="review.id" class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#f5f4ff] text-[#7561f7]">
                                        <MessageSquare class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-[#343241]">{{ review.author_name }}</div>
                                        <div v-if="review.title" class="mt-1 text-sm font-bold text-slate-700">{{ review.title }}</div>
                                        <div class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">{{ review.body }}</div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span v-if="review.is_verified_buyer" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                                                Перевірена покупка
                                            </span>
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">
                                                {{ review.source }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div v-if="review.product" class="text-sm">
                                    <div class="font-bold text-[#343241]">{{ review.product.name }}</div>
                                    <div v-if="review.product.sku" class="mt-1 text-xs font-semibold text-slate-500">{{ review.product.sku }}</div>
                                </div>
                                <span v-else class="text-sm font-semibold text-slate-400">Без товару</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="inline-flex items-center gap-1 rounded-lg bg-amber-50 px-3 py-2 text-sm font-bold text-amber-700">
                                    <Star class="h-4 w-4 fill-current" />
                                    {{ review.rating }}/5
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusClass(review.status)">
                                    {{ review.status_label }}
                                </span>
                                <div v-if="review.moderator" class="mt-2 text-xs font-medium text-slate-500">
                                    {{ review.moderator.name }}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-slate-500">
                                <div>{{ review.created_at }}</div>
                                <div v-if="review.published_at" class="mt-1 text-xs text-emerald-600">Опубліковано: {{ review.published_at }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        v-if="review.status !== 'approved'"
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-emerald-200 text-emerald-600 transition hover:bg-emerald-50"
                                        aria-label="Одобрити"
                                        @click="approveReview(review)"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="review.status !== 'rejected'"
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50"
                                        aria-label="Відхилити"
                                        @click="rejectReview(review)"
                                    >
                                        <XCircle class="h-4 w-4" />
                                    </button>
                                    <Link
                                        :href="route('admin.reviews.edit', review.id)"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                                        aria-label="Редагувати"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-red-300 hover:text-red-600"
                                        aria-label="Видалити"
                                        @click="destroyReview(review)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="reviews.data.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-sm font-semibold text-slate-500">
                                Відгуків ще немає.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="reviews.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                <template v-for="link in reviews.links" :key="link.label">
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
