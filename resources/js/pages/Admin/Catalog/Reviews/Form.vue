<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Star } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    review: {
        type: Object,
        required: true,
    },
    products: {
        type: Array,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
});

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    product_id: props.review.product_id ?? '',
    customer_id: props.review.customer_id ?? '',
    author_name: props.review.author_name ?? '',
    author_email: props.review.author_email ?? '',
    author_phone: props.review.author_phone ?? '',
    rating: props.review.rating ?? 5,
    title: props.review.title ?? '',
    body: props.review.body ?? '',
    status: props.review.status ?? 'pending',
    is_verified_buyer: props.review.is_verified_buyer ?? false,
    source: props.review.source ?? 'site',
    moderation_note: props.review.moderation_note ?? '',
    admin_reply: props.review.admin_reply ?? '',
});

const statusClass = computed(() => ({
    pending: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
}[form.status] ?? 'bg-slate-100 text-slate-600'));

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.reviews.update', props.review.id));
        return;
    }

    form.post(route('admin.reviews.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Редагування відгуку' : 'Створення відгуку'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Каталог</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">
                        {{ isEdit ? 'Редагування відгуку' : 'Створення відгуку' }}
                    </h1>
                </div>

                <Link
                    :href="route('admin.reviews.index')"
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
                    <h2 class="text-lg font-bold text-[#343241]">Відгук</h2>
                    <p class="mt-1 text-sm text-slate-500">Текст можна модерувати перед публікацією на сайті.</p>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="product_id">Товар</label>
                            <select
                                id="product_id"
                                v-model="form.product_id"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option value="">Без товару</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.label }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.product_id" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="source">Джерело</label>
                            <input
                                id="source"
                                v-model="form.source"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="site, instagram, manual"
                            />
                            <InputError class="mt-2" :message="form.errors.source" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="author_name">Імʼя автора</label>
                            <input
                                id="author_name"
                                v-model="form.author_name"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.author_name" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="rating">Рейтинг</label>
                            <div class="mt-2 flex items-center gap-3">
                                <select
                                    id="rating"
                                    v-model.number="form.rating"
                                    class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                >
                                    <option v-for="value in [5, 4, 3, 2, 1]" :key="value" :value="value">
                                        {{ value }} / 5
                                    </option>
                                </select>
                                <div class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-amber-50 px-3 py-2 text-sm font-bold text-amber-700">
                                    <Star class="h-4 w-4 fill-current" />
                                    {{ form.rating }}
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.rating" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="author_email">Email</label>
                            <input
                                id="author_email"
                                v-model="form.author_email"
                                type="email"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            />
                            <InputError class="mt-2" :message="form.errors.author_email" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="author_phone">Телефон</label>
                            <input
                                id="author_phone"
                                v-model="form.author_phone"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            />
                            <InputError class="mt-2" :message="form.errors.author_phone" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-bold text-slate-700" for="title">Заголовок</label>
                            <input
                                id="title"
                                v-model="form.title"
                                type="text"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Напр., Дуже мʼякі капці"
                            />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-sm font-bold text-slate-700" for="body">Текст відгуку</label>
                            <textarea
                                id="body"
                                v-model="form.body"
                                rows="7"
                                class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.body" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Відповідь магазину</h2>
                    <textarea
                        v-model="form.admin_reply"
                        rows="4"
                        class="mt-4 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        placeholder="Публічна відповідь магазину"
                    />
                    <InputError class="mt-2" :message="form.errors.admin_reply" />
                </div>
            </section>

            <aside class="space-y-5">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Модерація</h2>

                    <div class="mt-5">
                        <label class="text-sm font-bold text-slate-700" for="status">Статус</label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        >
                            <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.status" />
                        <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="statusClass">
                            {{ statusOptions.find((item) => item.value === form.status)?.label }}
                        </span>
                    </div>

                    <label class="mt-5 flex items-center justify-between gap-4 rounded-lg border border-slate-100 p-3">
                        <span>
                            <span class="block text-sm font-bold text-slate-700">Перевірена покупка</span>
                            <span class="block text-xs font-medium text-slate-500">Підсилює довіру на картці товару</span>
                        </span>
                        <input
                            v-model="form.is_verified_buyer"
                            type="checkbox"
                            class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                        />
                    </label>

                    <div class="mt-5">
                        <label class="text-sm font-bold text-slate-700" for="moderation_note">Нотатка модерації</label>
                        <textarea
                            id="moderation_note"
                            v-model="form.moderation_note"
                            rows="4"
                            class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="Внутрішня причина або коментар"
                        />
                        <InputError class="mt-2" :message="form.errors.moderation_note" />
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
