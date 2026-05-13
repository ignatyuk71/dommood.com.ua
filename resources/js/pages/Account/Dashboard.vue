<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Package, UserRound } from 'lucide-vue-next';

defineProps({
    customer: {
        type: Object,
        required: true,
    },
    orders: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Мій кабінет" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-sm font-bold text-slate-500">Профіль покупця</p>
                <h1 class="text-3xl font-black tracking-tight text-[#343241]">Мій кабінет</h1>
            </div>
        </template>

        <div class="grid gap-5 lg:grid-cols-[360px_minmax(0,1fr)]">
            <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-[#f0edff] text-[#7561f7]">
                        <UserRound class="h-6 w-6" />
                    </div>
                    <div>
                        <h2 class="text-xl font-black text-[#343241]">{{ customer.name }}</h2>
                        <p class="text-sm font-semibold text-slate-500">{{ customer.email }}</p>
                    </div>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3 text-sm font-bold">
                    <div class="rounded-lg bg-slate-50 p-4">
                        <span class="block text-xs uppercase text-slate-500">Замовлень</span>
                        {{ customer.orders_count }}
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <span class="block text-xs uppercase text-slate-500">Сума</span>
                        {{ customer.total_spent }} грн
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-xl font-black text-[#343241]">Історія замовлень</h2>
                    <p class="text-sm font-medium text-slate-500">Останні покупки в магазині.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    <div v-for="order in orders" :key="order.id" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-50 text-slate-500">
                                <Package class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="font-black text-[#343241]">#{{ order.order_number }}</div>
                                <div class="text-sm font-semibold text-slate-500">{{ order.created_at }} · {{ order.items_count }} шт.</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-[#343241]">{{ order.total }} грн</div>
                            <div class="text-xs font-bold uppercase text-slate-500">{{ order.status }}</div>
                        </div>
                    </div>
                    <div v-if="orders.length === 0" class="px-5 py-12 text-center">
                        <Package class="mx-auto h-9 w-9 text-slate-300" />
                        <div class="mt-3 font-black text-[#343241]">Замовлень ще немає</div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
