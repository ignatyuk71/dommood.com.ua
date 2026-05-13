<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Search, UserCheck, UserRound, UsersRound } from 'lucide-vue-next';
import { reactive } from 'vue';

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
});

const form = reactive({
    search: props.filters.search ?? '',
});

const submit = () => {
    router.get(route('admin.customers.index'), form, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Клієнти" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-slate-500">CRM</p>
                    <h1 class="text-3xl font-black tracking-tight text-[#343241]">Клієнти</h1>
                </div>
                <div class="grid grid-cols-3 gap-2 text-sm font-bold text-[#343241]">
                    <div class="rounded-lg bg-slate-50 px-4 py-3">
                        <span class="block text-xs uppercase text-slate-500">Усього</span>
                        {{ summary.total ?? 0 }}
                    </div>
                    <div class="rounded-lg bg-slate-50 px-4 py-3">
                        <span class="block text-xs uppercase text-slate-500">Акаунти</span>
                        {{ summary.registered ?? 0 }}
                    </div>
                    <div class="rounded-lg bg-slate-50 px-4 py-3">
                        <span class="block text-xs uppercase text-slate-500">З покупками</span>
                        {{ summary.withOrders ?? 0 }}
                    </div>
                </div>
            </div>
        </template>

        <section class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-xl font-black text-[#343241]">Список клієнтів</h2>
                    <p class="text-sm font-medium text-slate-500">Покупці з checkout та зареєстровані акаунти.</p>
                </div>
                <form class="flex min-w-[320px] max-w-xl flex-1 gap-2" @submit.prevent="submit">
                    <label class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="form.search"
                            type="search"
                            class="h-11 w-full rounded-lg border border-slate-200 pl-10 text-sm font-semibold shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="Пошук: імʼя, телефон або email"
                        >
                    </label>
                    <button class="h-11 rounded-lg bg-[#343241] px-5 text-sm font-bold text-white" type="submit">
                        Знайти
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Клієнт</th>
                            <th class="px-5 py-3">Контакти</th>
                            <th class="px-5 py-3">Замовлення</th>
                            <th class="px-5 py-3">Джерело</th>
                            <th class="px-5 py-3">Остання дія</th>
                            <th class="px-5 py-3">Акаунт</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="customer in customers.data" :key="customer.id" class="align-top">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#f0edff] text-[#7561f7]">
                                        <UserRound class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="font-black text-[#343241]">{{ customer.name }}</div>
                                        <div class="text-xs font-semibold text-slate-500">ID: {{ customer.id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-slate-600">
                                <div>{{ customer.phone || '—' }}</div>
                                <div class="text-slate-500">{{ customer.email || '—' }}</div>
                            </td>
                            <td class="px-5 py-4 text-sm font-bold text-[#343241]">
                                {{ customer.orders_count }} зам.
                                <div class="text-xs text-slate-500">{{ customer.total_spent }} грн</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                                    {{ customer.source }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm font-semibold text-slate-600">
                                {{ customer.last_order_at || customer.first_order_at || '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-black"
                                    :class="customer.registered ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    <UserCheck class="h-3.5 w-3.5" />
                                    {{ customer.registered ? 'Є акаунт' : 'Гість' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="customers.data.length === 0">
                            <td colspan="6" class="px-5 py-14 text-center">
                                <UsersRound class="mx-auto h-9 w-9 text-slate-300" />
                                <div class="mt-3 text-base font-black text-[#343241]">Клієнтів ще немає</div>
                                <div class="text-sm font-medium text-slate-500">Після замовлень або реєстрацій вони зʼявляться тут.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
