<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    Banknote,
    Building2,
    ChevronDown,
    Clipboard,
    CreditCard,
    Home,
    Loader2,
    Mail,
    MapPin,
    PackageOpen,
    Phone,
    Truck,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
});

const localOrder = ref({
    ...props.order,
    status_histories: props.order.status_histories ?? [],
});
const updatingStatus = ref(false);

const toast = (type, message) => {
    window.dispatchEvent(new CustomEvent('admin-toast', {
        detail: { type, message },
    }));
};

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

const copyButtonClass = 'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-[#f5f4ff] hover:text-[#7561f7]';

const deliveryCopyText = computed(() => [
    localOrder.value.delivery_city,
    localOrder.value.delivery_line,
].filter(Boolean).join(', '));

const displayValue = (value) => {
    const text = String(value ?? '').trim();

    return text !== '' ? text : '—';
};

const paymentUi = computed(() => localOrder.value.payment_ui ?? {
    tone: localOrder.value.payment_status === 'paid'
        ? 'paid'
        : ['cod', 'cash_on_delivery'].includes(localOrder.value.payment_method)
            ? 'cod'
            : 'unpaid',
    method_label: localOrder.value.payment_method_label,
    status_label: localOrder.value.payment_status_label,
    amount_label: localOrder.value.amount_due ? `До оплати: ${localOrder.value.amount_due}` : localOrder.value.total,
    paid_at: localOrder.value.paid_at,
    reference: localOrder.value.payment_reference,
});

const paymentStatusClass = (tone) => ({
    paid: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    cod: 'bg-amber-50 text-amber-700 ring-amber-100',
    pending: 'bg-sky-50 text-sky-700 ring-sky-100',
    failed: 'bg-red-50 text-red-700 ring-red-100',
    refunded: 'bg-violet-50 text-violet-700 ring-violet-100',
    unpaid: 'bg-slate-50 text-slate-600 ring-slate-100',
}[tone] ?? 'bg-slate-50 text-slate-600 ring-slate-100');

const paymentAccentClass = (tone) => ({
    paid: 'border-l-emerald-400',
    cod: 'border-l-amber-400',
    pending: 'border-l-sky-400',
    failed: 'border-l-red-400',
    refunded: 'border-l-violet-400',
    unpaid: 'border-l-slate-300',
}[tone] ?? 'border-l-slate-300');

const paymentIconClass = (tone) => ({
    paid: 'text-emerald-600',
    cod: 'text-amber-600',
    pending: 'text-sky-600',
    failed: 'text-red-600',
    refunded: 'text-violet-600',
    unpaid: 'text-slate-500',
}[tone] ?? 'text-slate-500');

const paymentIcon = (order) => ['cod', 'cash_on_delivery'].includes(order.payment_method) ? Banknote : CreditCard;

const paymentMetaRows = computed(() => [
    { label: 'Провайдер', value: displayValue(localOrder.value.payment_provider) },
    { label: 'ID транзакції', value: displayValue(localOrder.value.payment_reference), copyLabel: 'ID транзакції' },
    { label: 'Оплачено', value: displayValue(localOrder.value.paid_at) },
]);

const paymentTotalRows = computed(() => [
    { label: 'Товари', value: localOrder.value.subtotal },
    { label: 'Знижка', value: localOrder.value.discount_total },
    { label: 'Доставка', value: localOrder.value.delivery_price },
]);

const queryParamsFromUrl = (url) => {
    const text = String(url ?? '').trim();

    if (!text) {
        return new URLSearchParams();
    }

    try {
        return new URL(text, window.location.origin).searchParams;
    } catch {
        const query = text.includes('?') ? text.slice(text.indexOf('?') + 1) : text;

        return new URLSearchParams(query);
    }
};

const trackingParams = computed(() => {
    const params = new URLSearchParams(queryParamsFromUrl(localOrder.value.tracking?.landing_page_url));
    const referrerParams = queryParamsFromUrl(localOrder.value.tracking?.referrer_url);

    referrerParams.forEach((value, key) => {
        if (!params.has(key)) {
            params.set(key, value);
        }
    });

    return params;
});

const attribution = computed(() => {
    const sourceCode = localOrder.value.source?.code;
    const utmSource = localOrder.value.tracking?.utm_source;
    const utmMedium = localOrder.value.tracking?.utm_medium;
    const source = localOrder.value.source?.label || displayValue(utmSource);
    const channel = sourceCode === 'tiktok'
        ? 'TikTok Ads'
        : sourceCode === 'google'
            ? 'Google Ads'
            : ['meta', 'facebook', 'instagram'].includes(sourceCode)
                ? 'Meta Ads'
                : displayValue(utmMedium);

    return {
        source,
        channel,
        event_id: `order_${localOrder.value.order_number || localOrder.value.id}`,
        meta_capi: '—',
        utm: {
            source: displayValue(utmSource),
            medium: displayValue(utmMedium),
            campaign: displayValue(localOrder.value.tracking?.utm_campaign),
            content: displayValue(localOrder.value.tracking?.utm_content),
            term: displayValue(localOrder.value.tracking?.utm_term),
        },
        click_ids: {
            fbclid: displayValue(trackingParams.value.get('fbclid')),
            ttclid: displayValue(trackingParams.value.get('ttclid')),
            gclid: displayValue(trackingParams.value.get('gclid')),
            gbraid: displayValue(trackingParams.value.get('gbraid')),
            wbraid: displayValue(trackingParams.value.get('wbraid')),
        },
        entry: {
            landing_page_url: displayValue(localOrder.value.tracking?.landing_page_url),
            referrer_url: displayValue(localOrder.value.tracking?.referrer_url),
        },
    };
});

const statusHistory = computed(() => {
    if (localOrder.value.status_histories.length) {
        return localOrder.value.status_histories;
    }

    return [{
        id: 'current',
        to_status_label: localOrder.value.status_meta.label,
        created_at: localOrder.value.created_at,
        user_name: null,
        comment: null,
    }];
});

const updateStatus = async (event) => {
    const status = event.target.value;
    const previousStatus = localOrder.value.status;

    if (status === previousStatus) {
        return;
    }

    updatingStatus.value = true;

    try {
        const response = await window.axios.patch(route('admin.orders.status', localOrder.value.id), {
            status,
        });

        localOrder.value.status = status;
        localOrder.value.status_meta = response.data.status;

        if (response.data.history) {
            localOrder.value.status_histories = [
                response.data.history,
                ...localOrder.value.status_histories,
            ];
        }

        toast('success', 'Статус замовлення оновлено');
    } catch (error) {
        event.target.value = previousStatus;
        toast('error', error.response?.data?.message ?? 'Не вдалося оновити статус');
    } finally {
        updatingStatus.value = false;
    }
};
</script>

<template>
    <Head :title="`Замовлення #${localOrder.order_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Продажі</p>
                    <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#343241]">Замовлення #{{ localOrder.order_number }}</h1>
                    <p class="mt-1 text-sm font-medium text-slate-500">
                        Створено {{ localOrder.created_at }} · {{ localOrder.customer_name }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="localOrder.status_meta.class">
                        {{ localOrder.status_meta.label }}
                    </span>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset" :class="paymentStatusClass(paymentUi.tone)">
                        {{ paymentUi.status_label }}
                    </span>
                    <Link
                        :href="route('admin.orders.index')"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-[#343241] transition hover:border-[#7561f7] hover:text-[#7561f7]"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Назад
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-5">
            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-5">
                    <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="border-b border-slate-100 px-4 py-4">
                            <h2 class="text-lg font-bold text-[#343241]">Товари в замовленні</h2>
                            <p class="mt-0.5 text-xs font-medium text-slate-500">SKU, варіанти, кількість і сума по кожній позиції.</p>
                        </div>

                        <div class="divide-y divide-slate-100 p-4">
                            <div
                                v-for="item in localOrder.items"
                                :key="item.id"
                                class="grid gap-3 py-3 first:pt-0 last:pb-0 md:grid-cols-[86px_minmax(0,1fr)_90px_130px_130px] md:items-center"
                            >
                                <div class="h-20 w-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                    <img v-if="item.image_url" :src="item.image_url" alt="" class="h-full w-full object-cover" loading="lazy" />
                                    <PackageOpen v-else class="m-6 h-8 w-8 text-slate-300" />
                                </div>

                                <div class="min-w-0">
                                    <div class="font-bold leading-snug text-[#343241]">{{ item.product_name }}</div>
                                    <div class="mt-2 flex flex-wrap gap-1.5 text-xs font-bold text-slate-600">
                                        <span v-if="item.sku" class="rounded-md bg-slate-100 px-2 py-1">SKU: {{ item.sku }}</span>
                                        <span v-if="item.variant_name" class="rounded-md bg-slate-100 px-2 py-1">{{ item.variant_name }}</span>
                                        <span v-if="item.snapshot.size" class="rounded-md bg-slate-100 px-2 py-1">Розмір: {{ item.snapshot.size }}</span>
                                        <span v-if="item.snapshot.color" class="rounded-md bg-slate-100 px-2 py-1">Колір: {{ item.snapshot.color }}</span>
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">К-сть</div>
                                    <div class="mt-1 text-sm font-bold text-[#343241]">{{ item.quantity }} шт.</div>
                                </div>

                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Ціна</div>
                                    <div class="mt-1 text-sm font-bold text-[#343241]">{{ item.price }}</div>
                                </div>

                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Сума</div>
                                    <div class="mt-1 text-sm font-bold text-[#343241]">{{ item.total }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-slate-100 px-4 py-4">
                            <div class="min-w-[260px] rounded-lg bg-slate-50 px-4 py-3 text-right ring-1 ring-inset ring-slate-100">
                                <div class="text-xs font-bold uppercase text-slate-500">Разом за товари</div>
                                <div class="mt-1 text-xl font-bold text-[#343241]">{{ localOrder.subtotal }}</div>
                                <div class="mt-0.5 text-xs font-semibold text-slate-400">Без вартості доставки</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase text-slate-400">Фінанси</p>
                                    <h2 class="mt-1 text-lg font-bold text-[#343241]">Оплата</h2>
                                </div>
                                <span class="inline-flex w-max rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset" :class="paymentStatusClass(paymentUi.tone)">
                                    {{ paymentUi.status_label }}
                                </span>
                            </div>

                            <div class="mt-4 rounded-lg border border-l-4 border-slate-200 bg-slate-50/70 p-3" :class="paymentAccentClass(paymentUi.tone)">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white ring-1 ring-inset ring-slate-100" :class="paymentIconClass(paymentUi.tone)">
                                        <component :is="paymentIcon(localOrder)" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0">
                                        <div class="truncate text-base font-bold text-[#343241]">{{ paymentUi.method_label }}</div>
                                        <div class="mt-0.5 truncate text-xs font-semibold text-slate-500">{{ paymentUi.amount_label }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-100">
                                <div
                                    v-for="row in paymentMetaRows"
                                    :key="row.label"
                                    class="flex items-center justify-between gap-3 px-3 py-2.5"
                                >
                                    <span class="text-xs font-bold uppercase text-slate-500">{{ row.label }}</span>
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <span class="truncate text-right text-sm font-bold text-[#343241]" :title="row.value">{{ row.value }}</span>
                                        <button
                                            v-if="row.copyLabel && row.value !== '—'"
                                            :class="copyButtonClass"
                                            type="button"
                                            :title="`Копіювати ${row.label}`"
                                            @click="copyValue(row.value, row.copyLabel)"
                                        >
                                            <Clipboard class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2 text-sm">
                                <div v-for="row in paymentTotalRows" :key="row.label" class="flex items-center justify-between gap-3">
                                    <span class="font-semibold text-slate-500">{{ row.label }}</span>
                                    <span class="font-bold text-[#343241]">{{ row.value }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                                    <span class="text-base font-bold text-[#343241]">Разом</span>
                                    <span class="text-xl font-bold text-[#343241]">{{ localOrder.total }}</span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#343241] text-sm font-bold text-white transition hover:bg-[#292736]"
                                @click="copyValue(localOrder.total, 'Суму')"
                            >
                                <CreditCard class="h-4 w-4" />
                                Копіювати суму
                            </button>
                        </div>

                        <div v-if="localOrder.payment_transactions?.length" class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                            <h2 class="text-lg font-bold text-[#343241]">Транзакції</h2>
                            <div class="mt-4 divide-y divide-slate-100">
                                <div v-for="transaction in localOrder.payment_transactions" :key="transaction.id" class="py-3 first:pt-0 last:pb-0">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="font-bold text-[#343241]">{{ transaction.provider }}</div>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="transaction.status_class">
                                            {{ transaction.status_label }}
                                        </span>
                                    </div>
                                    <div class="mt-2 space-y-1 text-xs font-semibold text-slate-500">
                                        <div>Сума: <span class="text-[#343241]">{{ transaction.amount }}</span></div>
                                        <div>External order: <span class="break-all text-[#343241]">{{ transaction.external_order_id || '—' }}</span></div>
                                        <div>LiqPay ID: <span class="break-all text-[#343241]">{{ transaction.provider_transaction_id || '—' }}</span></div>
                                        <div>Оброблено: <span class="text-[#343241]">{{ transaction.processed_at || '—' }}</span></div>
                                        <div v-if="transaction.failure_reason" class="text-red-600">{{ transaction.failure_reason }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                            <h2 class="text-lg font-bold text-[#343241]">Атрибуція</h2>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-xs font-bold uppercase text-slate-500">Джерело</div>
                                    <div class="mt-1 break-words text-base font-bold text-[#343241]">{{ attribution.source }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-xs font-bold uppercase text-slate-500">Канал</div>
                                    <div class="mt-1 break-words text-base font-bold text-[#343241]">{{ attribution.channel }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-xs font-bold uppercase text-slate-500">Event ID</div>
                                    <div class="mt-1 break-words text-base font-bold text-[#343241]">{{ attribution.event_id }}</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <div class="text-xs font-bold uppercase text-slate-500">Meta CAPI</div>
                                    <div class="mt-1 break-words text-base font-bold text-[#343241]">{{ attribution.meta_capi }}</div>
                                </div>
                            </div>

                            <details class="group mt-4 rounded-lg bg-white p-3 shadow-[0_10px_28px_rgba(61,58,101,0.06)]">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-base font-bold text-[#343241]">
                                    <span class="inline-flex items-center gap-2">
                                        <BadgeCheck class="h-4 w-4 text-slate-500" />
                                        Деталі tracking
                                    </span>
                                    <ChevronDown class="h-5 w-5 text-slate-500 transition-transform group-open:rotate-180" />
                                </summary>

                                <div class="mt-4 space-y-4 text-sm font-medium text-slate-600">
                                    <div>
                                        <div class="mb-1 font-bold text-[#343241]">UTM</div>
                                        <div>utm_source: {{ attribution.utm.source }}</div>
                                        <div>utm_medium: {{ attribution.utm.medium }}</div>
                                        <div>utm_campaign: {{ attribution.utm.campaign }}</div>
                                        <div>utm_content: {{ attribution.utm.content }}</div>
                                        <div>utm_term: {{ attribution.utm.term }}</div>
                                    </div>

                                    <div>
                                        <div class="mb-1 font-bold text-[#343241]">Click IDs</div>
                                        <div class="break-all">fbclid: {{ attribution.click_ids.fbclid }}</div>
                                        <div class="break-all">ttclid: {{ attribution.click_ids.ttclid }}</div>
                                        <div class="break-all">gclid: {{ attribution.click_ids.gclid }}</div>
                                        <div class="break-all">gbraid: {{ attribution.click_ids.gbraid }}</div>
                                        <div class="break-all">wbraid: {{ attribution.click_ids.wbraid }}</div>
                                    </div>

                                    <div>
                                        <div class="mb-1 font-bold text-[#343241]">Точка входу</div>
                                        <div class="break-all">
                                            Landing URL:
                                            <a
                                                v-if="attribution.entry.landing_page_url !== '—'"
                                                :href="attribution.entry.landing_page_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-[#7561f7] hover:underline"
                                            >
                                                {{ attribution.entry.landing_page_url }}
                                            </a>
                                            <span v-else>—</span>
                                        </div>
                                        <div class="break-all">
                                            Referrer:
                                            <a
                                                v-if="attribution.entry.referrer_url !== '—'"
                                                :href="attribution.entry.referrer_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-[#7561f7] hover:underline"
                                            >
                                                {{ attribution.entry.referrer_url }}
                                            </a>
                                            <span v-else>—</span>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-[#343241]">Історія статусів</h2>
                                <p class="mt-0.5 text-xs font-medium text-slate-500">Останні зміни по замовленню.</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <select
                                    class="h-9 rounded-lg border-slate-200 py-1 text-sm font-semibold shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    :value="localOrder.status"
                                    :disabled="updatingStatus"
                                    @change="updateStatus"
                                >
                                    <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>
                                <Loader2 v-if="updatingStatus" class="h-4 w-4 animate-spin text-[#7561f7]" />
                            </div>
                        </div>

                        <div class="mt-5 space-y-4">
                            <div v-for="history in statusHistory" :key="history.id" class="relative pl-8">
                                <span class="absolute left-1 top-1 h-3 w-3 rounded-full bg-[#7561f7] ring-4 ring-[#f0edff]"></span>
                                <div class="text-sm font-bold text-[#343241]">{{ history.to_status_label }}</div>
                                <div class="mt-0.5 text-xs font-semibold text-slate-500">
                                    {{ history.created_at }}
                                    <span v-if="history.user_name"> · {{ history.user_name }}</span>
                                </div>
                                <p v-if="history.comment" class="mt-2 text-sm text-slate-600">{{ history.comment }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-5">
                    <div class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <h2 class="text-lg font-bold text-[#343241]">Клієнт</h2>
                        <div class="mt-4 divide-y divide-slate-100">
                            <div class="flex items-center gap-3 py-3">
                                <User class="h-5 w-5 text-[#7561f7]" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold uppercase text-slate-500">ПІБ</div>
                                    <div class="truncate text-sm font-bold text-[#343241]">{{ localOrder.customer_name }}</div>
                                </div>
                                <button :class="copyButtonClass" type="button" title="Копіювати ПІБ" @click="copyValue(localOrder.customer_name, 'ПІБ')">
                                    <Clipboard class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="flex items-center gap-3 py-3">
                                <Phone class="h-5 w-5 text-[#7561f7]" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold uppercase text-slate-500">Телефон</div>
                                    <div class="truncate text-sm font-bold text-[#7561f7]">{{ localOrder.customer_phone || '—' }}</div>
                                </div>
                                <button v-if="localOrder.customer_phone" :class="copyButtonClass" type="button" title="Копіювати телефон" @click="copyValue(localOrder.customer_phone, 'Телефон')">
                                    <Clipboard class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="flex items-center gap-3 py-3">
                                <Mail class="h-5 w-5 text-[#7561f7]" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold uppercase text-slate-500">Email</div>
                                    <div class="truncate text-sm font-bold text-[#343241]">{{ localOrder.customer_email || '—' }}</div>
                                </div>
                                <button v-if="localOrder.customer_email" :class="copyButtonClass" type="button" title="Копіювати email" @click="copyValue(localOrder.customer_email, 'Email')">
                                    <Clipboard class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <h2 class="text-lg font-bold text-[#343241]">Доставка</h2>
                        <div class="mt-4 divide-y divide-slate-100">
                            <div class="flex items-center gap-3 py-3">
                                <Truck class="h-5 w-5 text-[#7561f7]" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold uppercase text-slate-500">Спосіб</div>
                                    <div class="text-sm font-bold text-[#343241]">{{ localOrder.delivery_method_label }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 py-3">
                                <MapPin class="h-5 w-5 text-[#7561f7]" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold uppercase text-slate-500">Місто</div>
                                    <div class="truncate text-sm font-bold text-[#343241]">{{ localOrder.delivery_city || '—' }}</div>
                                </div>
                                <button v-if="localOrder.delivery_city" :class="copyButtonClass" type="button" title="Копіювати місто" @click="copyValue(localOrder.delivery_city, 'Місто')">
                                    <Clipboard class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="flex items-center gap-3 py-3">
                                <Building2 class="h-5 w-5 text-[#7561f7]" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold uppercase text-slate-500">Відділення / поштомат</div>
                                    <div class="text-sm font-bold text-[#343241]">{{ localOrder.delivery_branch || '—' }}</div>
                                </div>
                                <button v-if="localOrder.delivery_branch" :class="copyButtonClass" type="button" title="Копіювати відділення" @click="copyValue(localOrder.delivery_branch, 'Відділення')">
                                    <Clipboard class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="flex items-center gap-3 py-3">
                                <Home class="h-5 w-5 text-[#7561f7]" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold uppercase text-slate-500">Адреса курʼєра</div>
                                    <div class="text-sm font-bold text-[#343241]">{{ localOrder.delivery_address || '—' }}</div>
                                </div>
                                <button v-if="localOrder.delivery_address" :class="copyButtonClass" type="button" title="Копіювати адресу" @click="copyValue(localOrder.delivery_address, 'Адресу')">
                                    <Clipboard class="h-4 w-4" />
                                </button>
                            </div>

                            <button
                                v-if="deliveryCopyText"
                                type="button"
                                class="mt-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg border border-[#7561f7] text-sm font-bold text-[#7561f7] transition hover:bg-[#f5f4ff]"
                                @click="copyValue(deliveryCopyText, 'Доставку')"
                            >
                                <Clipboard class="h-4 w-4" />
                                Копіювати доставку
                            </button>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
