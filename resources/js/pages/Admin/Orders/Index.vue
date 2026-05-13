<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    ChevronDown,
    Clipboard,
    CreditCard,
    Eye,
    Loader2,
    PackageOpen,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    orders: {
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
    statusGroups: {
        type: Array,
        required: true,
    },
});

const localOrders = ref(props.orders.data.map((order) => ({ ...order })));
const expandedIds = ref(new Set());
const updatingStatus = ref({});
const highlightedIds = ref({});
const search = ref(props.filters.search ?? '');

const statusGroupOptions = computed(() => [
    { value: '', label: 'Усі' },
    ...props.statusGroups,
]);

watch(() => props.orders.data, (orders) => {
    localOrders.value = orders.map((order) => ({ ...order }));
}, { deep: true });

const toast = (type, message) => {
    window.dispatchEvent(new CustomEvent('admin-toast', {
        detail: { type, message },
    }));
};

const applyFilters = (extra = {}) => {
    const params = {
        search: search.value || undefined,
        status_group: props.filters.status_group || undefined,
        status: props.filters.status || undefined,
        ...extra,
    };

    Object.keys(params).forEach((key) => {
        if (!params[key]) {
            delete params[key];
        }
    });

    router.get(route('admin.orders.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const setStatusGroup = (statusGroup) => {
    applyFilters({
        status_group: statusGroup || undefined,
        status: undefined,
    });
};

const setStatus = (status) => {
    applyFilters({
        status: status || undefined,
        status_group: undefined,
    });
};

const toggleDetails = (orderId) => {
    const next = new Set(expandedIds.value);

    if (next.has(orderId)) {
        next.delete(orderId);
    } else {
        next.add(orderId);
    }

    expandedIds.value = next;
};

const isExpanded = (orderId) => expandedIds.value.has(orderId);

const copyValue = async (value, label = 'Поле') => {
    const text = String(value ?? '').trim();

    if (!text) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const input = document.createElement('textarea');
            input.value = text;
            input.setAttribute('readonly', 'readonly');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
        }

        toast('success', `${label} скопійовано`);
    } catch {
        toast('error', 'Не вдалося скопіювати');
    }
};

const copyButtonClass = 'inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-[#f5f4ff] hover:text-[#7561f7]';

const sourceDots = {
    meta: 'bg-blue-600',
    facebook: 'bg-blue-600',
    instagram: 'bg-blue-600',
    tiktok: 'bg-pink-500',
    google: 'bg-red-500',
    other: 'bg-slate-500',
};

const sourceDotFor = (source) => sourceDots[source?.code] ?? sourceDots.other;

const sourceLabelFor = (source) => {
    if (['facebook', 'instagram'].includes(source?.code)) {
        return 'Meta';
    }

    return source?.label ?? 'Інше';
};

const paymentCardClass = (tone) => ({
    paid: 'border-l-emerald-400',
    cod: 'border-l-amber-400',
    pending: 'border-l-sky-400',
    failed: 'border-l-red-400',
    refunded: 'border-l-violet-400',
    unpaid: 'border-l-slate-300',
}[tone] ?? 'border-l-slate-300');

const paymentPillClass = (tone) => ({
    paid: 'bg-slate-50 text-emerald-700 ring-slate-200',
    cod: 'bg-slate-50 text-amber-700 ring-slate-200',
    pending: 'bg-slate-50 text-sky-700 ring-slate-200',
    failed: 'bg-slate-50 text-red-700 ring-slate-200',
    refunded: 'bg-slate-50 text-violet-700 ring-slate-200',
    unpaid: 'bg-slate-50 text-slate-600 ring-slate-100',
}[tone] ?? 'bg-slate-50 text-slate-600 ring-slate-100');

const paymentIconClass = (tone) => ({
    paid: 'text-emerald-600',
    cod: 'text-amber-600',
    pending: 'text-sky-600',
    failed: 'text-red-600',
    refunded: 'text-violet-600',
    unpaid: 'text-slate-500',
}[tone] ?? 'text-slate-500');

const paymentIcon = (order) => ['cod', 'cash_on_delivery'].includes(order.payment_method) ? Banknote : CreditCard;

const highlightOrderRow = (orderId) => {
    highlightedIds.value = {
        ...highlightedIds.value,
        [orderId]: true,
    };

    window.setTimeout(() => {
        highlightedIds.value = {
            ...highlightedIds.value,
            [orderId]: false,
        };
    }, 1000);
};

const updateStatus = async (order, event) => {
    const status = event.target.value;
    const previousStatus = order.status;

    if (status === previousStatus) {
        return;
    }

    updatingStatus.value = {
        ...updatingStatus.value,
        [order.id]: true,
    };

    try {
        const response = await window.axios.patch(route('admin.orders.status', order.id), {
            status,
        });

        order.status = status;
        order.status_meta = response.data.status;
        highlightOrderRow(order.id);
        toast('success', 'Статус замовлення оновлено');
    } catch (error) {
        event.target.value = previousStatus;
        toast('error', error.response?.data?.message ?? 'Не вдалося оновити статус');
    } finally {
        updatingStatus.value = {
            ...updatingStatus.value,
            [order.id]: false,
        };
    }
};

const destroyOrder = (order) => {
    if (!window.confirm(`Видалити замовлення #${order.order_number}?`)) {
        return;
    }

    router.delete(route('admin.orders.destroy', order.id), {
        preserveScroll: true,
    });
};

const deliveryCopyText = (order) => [
    order.delivery_city,
    order.delivery_line,
].filter(Boolean).join(', ');
</script>

<template>
    <Head title="Замовлення" />

    <AuthenticatedLayout>
        <section class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="border-b border-slate-100 px-4 py-3">
                <div class="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-[#343241]">Список замовлень</h2>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">Компактний список для менеджера: покупець, доставка, оплата, товари й статус.</p>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="group in statusGroupOptions"
                            :key="group.value || 'all'"
                            type="button"
                            class="h-8 rounded-lg border px-3 text-xs font-bold transition"
                            :class="(filters.status_group || '') === group.value && !filters.status
                                ? 'border-[#7561f7] bg-[#7561f7] text-white shadow-[0_8px_20px_rgba(117,97,247,0.18)]'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]'"
                            @click="setStatusGroup(group.value)"
                        >
                            {{ group.label }}
                        </button>
                    </div>
                </div>

                <form class="mt-3 grid gap-2 lg:grid-cols-[minmax(280px,1fr)_180px_96px]" @submit.prevent="applyFilters()">
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            class="h-9 w-full rounded-lg border-slate-200 pl-8 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="Пошук: номер, телефон, покупець, місто"
                        />
                    </div>
                    <select
                        :value="filters.status || ''"
                        class="h-9 rounded-lg border-slate-200 py-1 text-sm font-semibold shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        @change="setStatus($event.target.value)"
                    >
                        <option value="">Усі статуси</option>
                        <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                    <button
                        type="submit"
                        class="h-9 rounded-lg bg-[#343241] px-4 text-sm font-bold text-white transition hover:bg-[#292736]"
                    >
                        Знайти
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1520px] table-fixed divide-y divide-slate-100">
                    <colgroup>
                        <col class="w-[200px]" />
                        <col class="w-[145px]" />
                        <col class="w-[220px]" />
                        <col class="w-[120px]" />
                        <col class="w-[180px]" />
                        <col class="w-[270px]" />
                        <col class="w-[250px]" />
                        <col class="w-[96px]" />
                    </colgroup>
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase text-slate-500">Номер і дата</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase text-slate-500">Товари</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase text-slate-500">Покупець</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold uppercase text-slate-500">Сума</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase text-slate-500">Статус</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase text-slate-500">Доставка</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase text-slate-500">Оплата</th>
                            <th class="px-3 py-2.5 text-right text-[11px] font-bold uppercase text-slate-500">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="order in localOrders" :key="order.id">
                            <tr
                                class="align-top transition-colors duration-300 hover:bg-slate-50/70"
                                :class="highlightedIds[order.id] ? 'bg-emerald-50' : 'bg-white'"
                            >
                                <td class="px-3 py-2.5">
                                    <div class="flex items-start gap-2">
                                        <button
                                            type="button"
                                            class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-md text-slate-500 transition hover:bg-[#f5f4ff] hover:text-[#7561f7]"
                                            :aria-label="isExpanded(order.id) ? 'Згорнути деталі' : 'Показати деталі'"
                                            @click="toggleDetails(order.id)"
                                        >
                                            <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="isExpanded(order.id) ? 'rotate-180' : '-rotate-90'" />
                                        </button>
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-bold text-[#7561f7]">#{{ order.order_number }}</div>
                                            <div class="mt-0.5 text-xs font-semibold text-slate-500">{{ order.created_at }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5">
                                    <div class="flex items-center">
                                        <div
                                            v-for="(thumb, index) in order.thumbs"
                                            :key="`${order.id}-${thumb}`"
                                            class="h-16 w-16 overflow-hidden rounded-lg border-2 border-white bg-slate-100 shadow-sm"
                                            :class="index ? '-ml-3' : ''"
                                        >
                                            <img :src="thumb" alt="" class="h-full w-full object-cover" loading="lazy" />
                                        </div>
                                        <div v-if="!order.thumbs.length" class="inline-flex h-16 w-16 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                            <PackageOpen class="h-5 w-5" />
                                        </div>
                                        <span v-if="order.items_count > order.thumbs.length" class="-ml-2 rounded-full bg-[#f0edff] px-2 py-1 text-xs font-bold text-[#7561f7]">
                                            +{{ order.items_count - order.thumbs.length }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-1">
                                        <div class="truncate text-sm font-bold text-[#343241]">{{ order.customer_name }}</div>
                                        <button :class="copyButtonClass" type="button" title="Копіювати покупця" @click="copyValue(order.customer_name, 'Покупця')">
                                            <Clipboard class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1 text-xs font-semibold text-slate-500">
                                        <span>{{ order.customer_phone || '—' }}</span>
                                        <button v-if="order.customer_phone" :class="copyButtonClass" type="button" title="Копіювати телефон" @click="copyValue(order.customer_phone, 'Телефон')">
                                            <Clipboard class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1 text-xs font-semibold text-slate-500">
                                        <span class="truncate">{{ order.customer_email || '—' }}</span>
                                        <button v-if="order.customer_email" :class="copyButtonClass" type="button" title="Копіювати email" @click="copyValue(order.customer_email, 'Email')">
                                            <Clipboard class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5 text-right">
                                    <div class="text-sm font-bold text-[#343241]">{{ order.total }}</div>
                                    <span class="mt-1 inline-flex items-center justify-end gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset" :class="order.source.class">
                                        <span class="h-2 w-2 rounded-full" :class="sourceDotFor(order.source)"></span>
                                        {{ sourceLabelFor(order.source) }}
                                    </span>
                                </td>

                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="order.status_meta.class">
                                            {{ order.status_meta.label }}
                                        </span>
                                        <Loader2 v-if="updatingStatus[order.id]" class="h-4 w-4 animate-spin text-[#7561f7]" />
                                    </div>
                                    <select
                                        class="mt-1.5 h-8 w-full rounded-lg border-slate-200 py-1 text-xs font-semibold shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                        :value="order.status"
                                        :disabled="updatingStatus[order.id]"
                                        @change="updateStatus(order, $event)"
                                    >
                                        <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                                            {{ status.label }}
                                        </option>
                                    </select>
                                </td>

                                <td class="px-3 py-2.5">
                                    <div class="truncate text-sm font-bold text-[#343241]">{{ order.delivery_method_label }}</div>
                                    <div class="flex items-center gap-1 text-xs font-semibold text-slate-500">
                                        <span class="truncate" :title="order.delivery_line">{{ order.delivery_line || 'Дані доставки ще не вказано' }}</span>
                                        <button v-if="order.delivery_line" :class="copyButtonClass" type="button" title="Копіювати відділення" @click="copyValue(order.delivery_line, 'Відділення')">
                                            <Clipboard class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1 text-xs font-semibold text-slate-500">
                                        <span>{{ order.delivery_city || '—' }}</span>
                                        <button v-if="order.delivery_city" :class="copyButtonClass" type="button" title="Копіювати місто" @click="copyValue(order.delivery_city, 'Місто')">
                                            <Clipboard class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5">
                                    <div class="rounded-lg border border-l-4 border-slate-200 bg-white px-3 py-2 shadow-[0_8px_18px_rgba(61,58,101,0.05)]" :class="paymentCardClass(order.payment_ui.tone)">
                                        <div class="flex items-start gap-2.5">
                                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-50 ring-1 ring-inset ring-slate-100" :class="paymentIconClass(order.payment_ui.tone)">
                                                <component :is="paymentIcon(order)" class="h-3.5 w-3.5" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex min-w-0 items-center justify-between gap-2">
                                                    <div class="truncate text-sm font-bold text-[#343241]" :title="order.payment_ui.method_label">{{ order.payment_ui.method_label }}</div>
                                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset" :class="paymentPillClass(order.payment_ui.tone)">
                                                        {{ order.payment_ui.status_label }}
                                                    </span>
                                                </div>
                                                <div class="mt-0.5 truncate text-xs font-medium text-slate-500" :title="order.payment_ui.amount_label">{{ order.payment_ui.amount_label }}</div>
                                                <div v-if="order.payment_ui.paid_at || order.payment_ui.reference" class="mt-1 flex min-w-0 flex-wrap gap-x-2 gap-y-0.5 text-[11px] font-semibold text-slate-400">
                                                    <span v-if="order.payment_ui.paid_at">Опл. {{ order.payment_ui.paid_at }}</span>
                                                    <span v-if="order.payment_ui.reference" class="truncate">ID {{ order.payment_ui.reference }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="route('admin.orders.show', order.id)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#7561f7] text-[#7561f7] transition hover:bg-[#f5f4ff]"
                                            title="Деталі"
                                            aria-label="Деталі"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-200 text-red-500 transition hover:bg-red-50"
                                            aria-label="Видалити"
                                            @click="destroyOrder(order)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="isExpanded(order.id)" class="bg-white">
                                <td colspan="8" class="px-4 pb-4">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
                                        <div class="grid gap-4 lg:grid-cols-3">
                                            <div>
                                                <div class="text-xs font-bold uppercase text-slate-500">Покупець</div>
                                                <div class="mt-2 text-sm font-bold text-[#343241]">{{ order.customer_name }}</div>
                                                <div class="text-sm text-slate-600">{{ order.customer_phone }}</div>
                                                <div class="text-sm text-slate-600">{{ order.customer_email || '—' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs font-bold uppercase text-slate-500">Доставка</div>
                                                <div class="mt-2 text-sm font-bold text-[#343241]">{{ order.delivery_method_label }}</div>
                                                <div class="text-sm text-slate-600">{{ order.delivery_line || '—' }}</div>
                                                <div class="text-sm text-slate-600">{{ order.delivery_city || '—' }}</div>
                                                <div class="text-sm text-slate-600">Вартість: {{ order.delivery_price }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs font-bold uppercase text-slate-500">Оплата</div>
                                                <div class="mt-2 text-sm font-bold text-[#343241]">{{ order.payment_method_label }}</div>
                                                <div class="text-sm text-slate-600">{{ order.payment_status_label }}</div>
                                                <div class="text-sm text-slate-600">Товари: {{ order.subtotal }}</div>
                                                <div class="text-sm text-slate-600">Разом: {{ order.total }}</div>
                                            </div>
                                        </div>

                                        <div v-if="order.comment || order.manager_comment" class="mt-4 grid gap-3 lg:grid-cols-2">
                                            <div v-if="order.comment" class="rounded-lg bg-white p-3 text-sm text-slate-600">
                                                <div class="mb-1 text-xs font-bold uppercase text-slate-500">Коментар клієнта</div>
                                                {{ order.comment }}
                                            </div>
                                            <div v-if="order.manager_comment" class="rounded-lg bg-white p-3 text-sm text-slate-600">
                                                <div class="mb-1 text-xs font-bold uppercase text-slate-500">Коментар менеджера</div>
                                                {{ order.manager_comment }}
                                            </div>
                                        </div>

                                        <div class="mt-4 overflow-x-auto rounded-lg bg-white">
                                            <table class="min-w-full divide-y divide-slate-100">
                                                <tbody class="divide-y divide-slate-100">
                                                    <tr v-for="item in order.items" :key="item.id">
                                                        <td class="w-[76px] px-3 py-3">
                                                            <div class="h-14 w-14 overflow-hidden rounded-lg bg-slate-100">
                                                                <img v-if="item.image_url" :src="item.image_url" alt="" class="h-full w-full object-cover" loading="lazy" />
                                                                <PackageOpen v-else class="m-4 h-6 w-6 text-slate-300" />
                                                            </div>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <div class="text-sm font-bold text-[#343241]">{{ item.product_name }}</div>
                                                            <div class="mt-1 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                                                <span v-if="item.sku">Артикул: {{ item.sku }}</span>
                                                                <span v-if="item.variant_name">{{ item.variant_name }}</span>
                                                                <span v-if="item.snapshot.size">Розмір: {{ item.snapshot.size }}</span>
                                                                <span v-if="item.snapshot.color">Колір: {{ item.snapshot.color }}</span>
                                                            </div>
                                                        </td>
                                                        <td class="px-3 py-3 text-right text-sm font-bold text-[#343241]">{{ item.quantity }} шт.</td>
                                                        <td class="px-3 py-3 text-right text-sm font-semibold text-slate-600">{{ item.price }}</td>
                                                        <td class="px-3 py-3 text-right text-sm font-bold text-[#343241]">{{ item.total }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="localOrders.length === 0">
                            <td colspan="8" class="px-4 py-12 text-center">
                                <PackageOpen class="mx-auto h-8 w-8 text-slate-300" />
                                <div class="mt-3 text-sm font-bold text-[#343241]">Замовлень ще немає</div>
                                <p class="mt-1 text-xs font-medium text-slate-500">Коли checkout буде підключено, нові замовлення зʼявляться тут.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="orders.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-4">
                <template v-for="link in orders.links" :key="link.label">
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
