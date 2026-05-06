<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarDays,
    CircleDollarSign,
    PackageCheck,
    ShoppingCart,
    SlidersHorizontal,
    TrendingUp,
    UsersRound,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    dashboard: {
        type: Object,
        required: true,
    },
});

const money = (cents) =>
    new Intl.NumberFormat('uk-UA', {
        style: 'currency',
        currency: 'UAH',
        maximumFractionDigits: 2,
    }).format((cents || 0) / 100);

const number = (value) => new Intl.NumberFormat('uk-UA').format(value || 0);

const stats = computed(() => props.dashboard.stats);

const metricCards = computed(() => [
    {
        label: 'Замовлення',
        value: number(stats.value.orders_count),
        detail: 'Нові продажі за період',
        icon: ShoppingCart,
        tone: 'violet',
    },
    {
        label: 'Дохід (всього)',
        value: money(stats.value.revenue_cents),
        detail: 'Сума підтверджених замовлень',
        icon: CircleDollarSign,
        tone: 'green',
    },
    {
        label: 'Середній чек',
        value: money(stats.value.average_order_cents),
        detail: 'AOV за вибраний період',
        icon: BarChart3,
        tone: 'cyan',
    },
    {
        label: 'Унікальні клієнти',
        value: number(stats.value.unique_customers),
        detail: `${number(stats.value.sold_units)} проданих одиниць`,
        icon: UsersRound,
        tone: 'violet',
    },
    {
        label: 'Активні товари',
        value: number(stats.value.active_products),
        detail: `${number(stats.value.customers_total)} клієнтів у CRM`,
        icon: PackageCheck,
        tone: 'orange',
    },
]);

const chartWidth = 920;
const chartHeight = 290;
const chartPadding = { top: 24, right: 28, bottom: 46, left: 46 };

const chart = computed(() => props.dashboard.chart);
const maxOrders = computed(() => Math.max(...chart.value.orders, 1));
const maxRevenue = computed(() => Math.max(...chart.value.revenue, 1));

const points = (series, maxValue) => {
    const usableWidth = chartWidth - chartPadding.left - chartPadding.right;
    const usableHeight = chartHeight - chartPadding.top - chartPadding.bottom;
    const lastIndex = Math.max(series.length - 1, 1);

    return series.map((value, index) => {
        const x = chartPadding.left + (usableWidth / lastIndex) * index;
        const y = chartPadding.top + usableHeight - (usableHeight * value) / maxValue;

        return [Number(x.toFixed(2)), Number(y.toFixed(2))];
    });
};

const pathFromPoints = (chartPoints) =>
    chartPoints
        .map(([x, y], index) => `${index === 0 ? 'M' : 'L'} ${x} ${y}`)
        .join(' ');

const ordersLine = computed(() => pathFromPoints(points(chart.value.orders, maxOrders.value)));
const revenuePoints = computed(() => points(chart.value.revenue, maxRevenue.value));
const revenueLine = computed(() => pathFromPoints(revenuePoints.value));
const revenueArea = computed(() => {
    const first = revenuePoints.value[0] || [chartPadding.left, chartHeight - chartPadding.bottom];
    const last = revenuePoints.value[revenuePoints.value.length - 1] || first;
    const baseline = chartHeight - chartPadding.bottom;

    return `${revenueLine.value} L ${last[0]} ${baseline} L ${first[0]} ${baseline} Z`;
});

const gridLines = computed(() => [0, 1, 2, 3, 4].map((step) => {
    const usableHeight = chartHeight - chartPadding.top - chartPadding.bottom;
    return chartPadding.top + (usableHeight / 4) * step;
}));

const visibleLabels = computed(() => chart.value.labels.filter((_, index) => index % 3 === 0));

const sourceColors = {
    Meta: '#2f66e8',
    TikTok: '#ec4899',
    Google: '#ef4444',
    Інше: '#64748b',
};

const sourceTotal = computed(() =>
    props.dashboard.sources.reduce((sum, source) => sum + source.orders, 0),
);

const sourceSegments = computed(() => {
    if (sourceTotal.value === 0) {
        return [{ label: 'Немає даних', percent: 100, offset: 0, color: '#e7e9f2' }];
    }

    let offset = 0;

    return props.dashboard.sources.map((source) => {
        const percent = (source.orders / sourceTotal.value) * 100;
        const segment = {
            ...source,
            percent,
            offset,
            color: sourceColors[source.label],
        };
        offset -= percent;

        return segment;
    });
});

const toneClasses = {
    violet: 'bg-[#eeeaff] text-[#7561f7]',
    green: 'bg-[#e8fbef] text-[#22c55e]',
    cyan: 'bg-[#e8fbff] text-[#25c7de]',
    orange: 'bg-[#fff1e6] text-[#fb923c]',
};
</script>

<template>
    <Head title="Адмінка" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-[#343241] md:text-4xl">
                        Адмінка
                    </h1>
                    <p class="mt-2 text-sm font-medium text-slate-500">
                        Операційний центр магазину: продажі, каталог, клієнти й tracking.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-[#343241] shadow-sm transition hover:border-[#7561f7]/40 hover:text-[#29277f]">
                        <SlidersHorizontal class="h-4 w-4" />
                        Налаштувати
                    </button>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-[#29277f] px-4 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-900/20 transition hover:bg-[#232169]">
                        <Zap class="h-4 w-4" />
                        Швидка дія
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
                    <button class="flex min-h-14 items-center justify-between rounded-lg border border-[#cfd8e8] px-4 text-left text-sm font-bold text-[#474456] transition hover:border-[#7561f7]/60">
                        <span class="flex items-center gap-3">
                            <CalendarDays class="h-5 w-5 text-[#6d5df6]" />
                            {{ dashboard.period.label }}
                        </span>
                        <span class="text-xl leading-none text-slate-400">⌄</span>
                    </button>
                    <div class="rounded-lg border border-slate-200 bg-[#fbfcff] px-4 py-3">
                        <div class="text-sm font-bold text-[#343241]">30 дн. • Реальні дані магазину</div>
                        <div class="mt-1 text-sm text-slate-500">
                            {{ number(stats.orders_count) }} замовлень · {{ money(stats.revenue_cents) }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="card in metricCards"
                    :key="card.label"
                    class="min-h-[148px] rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]"
                >
                    <div class="flex h-full items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#70809d]">{{ card.label }}</p>
                            <p class="mt-2 text-3xl font-bold tracking-wide text-[#454150]">{{ card.value }}</p>
                            <p class="mt-3 text-xs font-medium text-slate-500">{{ card.detail }}</p>
                        </div>
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full" :class="toneClasses[card.tone]">
                            <component :is="card.icon" class="h-6 w-6" />
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-[#343241]">Динаміка: замовлення та дохід</h2>
                            <p class="mt-1 text-sm text-slate-500">Графік формується з таблиці замовлень за вибраний період.</p>
                        </div>
                        <div class="flex items-center gap-4 text-sm font-semibold text-slate-500">
                            <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-[#3f72f0]"></span>Замовлення</span>
                            <span class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-[#1da052]"></span>Дохід</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="min-w-[760px]">
                            <defs>
                                <linearGradient id="revenueFill" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#22c55e" stop-opacity="0.24" />
                                    <stop offset="100%" stop-color="#22c55e" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <g>
                                <line
                                    v-for="line in gridLines"
                                    :key="line"
                                    :x1="chartPadding.left"
                                    :x2="chartWidth - chartPadding.right"
                                    :y1="line"
                                    :y2="line"
                                    stroke="#dfe3ec"
                                    stroke-dasharray="6 6"
                                />
                            </g>
                            <path :d="revenueArea" fill="url(#revenueFill)" />
                            <path :d="ordersLine" fill="none" stroke="#3f72f0" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" />
                            <path :d="revenueLine" fill="none" stroke="#1da052" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" />
                            <g>
                                <text
                                    v-for="(label, index) in visibleLabels"
                                    :key="label"
                                    :x="chartPadding.left + ((chartWidth - chartPadding.left - chartPadding.right) / Math.max(visibleLabels.length - 1, 1)) * index"
                                    :y="chartHeight - 12"
                                    text-anchor="middle"
                                    fill="#6b7280"
                                    font-size="13"
                                    font-weight="600"
                                >
                                    {{ label }}
                                </text>
                            </g>
                            <text :x="chartPadding.left" y="18" fill="#6b7280" font-size="12" font-weight="700">Замовлення</text>
                            <text :x="chartWidth - chartPadding.right" y="18" text-anchor="end" fill="#6b7280" font-size="12" font-weight="700">Дохід, грн</text>
                        </svg>
                    </div>
                </article>

                <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-[#343241]">Джерела замовлень</h2>
                            <p class="mt-1 text-sm text-slate-500">Meta / TikTok / Google / Інше</p>
                        </div>
                        <TrendingUp class="h-5 w-5 text-[#7561f7]" />
                    </div>

                    <div class="flex justify-center">
                        <svg viewBox="0 0 180 180" class="h-64 w-64">
                            <circle cx="90" cy="90" r="58" fill="none" stroke="#eef1f7" stroke-width="28" />
                            <circle
                                v-for="segment in sourceSegments"
                                :key="segment.label"
                                cx="90"
                                cy="90"
                                r="58"
                                fill="none"
                                :stroke="segment.color"
                                stroke-width="28"
                                stroke-linecap="butt"
                                pathLength="100"
                                :stroke-dasharray="`${segment.percent} ${100 - segment.percent}`"
                                :stroke-dashoffset="segment.offset"
                                transform="rotate(-90 90 90)"
                            />
                            <text x="90" y="86" text-anchor="middle" fill="#343241" font-size="24" font-weight="800">{{ number(sourceTotal) }}</text>
                            <text x="90" y="108" text-anchor="middle" fill="#70809d" font-size="12" font-weight="700">замовлень</text>
                        </svg>
                    </div>

                    <div class="mt-2 space-y-3">
                        <div
                            v-for="source in dashboard.sources"
                            :key="source.label"
                            class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                        >
                            <span class="inline-flex items-center gap-2 text-sm font-bold text-[#454150]">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: sourceColors[source.label] }"></span>
                                {{ source.label }}
                            </span>
                            <span class="text-sm font-semibold text-slate-600">
                                {{ number(source.orders) }} / {{ money(source.revenue_cents) }}
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <section class="grid gap-6 lg:grid-cols-3">
                <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Операційні пріоритети</h2>
                    <div class="mt-4 space-y-4">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="text-sm font-bold text-[#343241]">Каталог</div>
                            <p class="mt-1 text-sm text-slate-500">Наступний етап: CRUD категорій, меню та товарів.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="text-sm font-bold text-[#343241]">Checkout</div>
                            <p class="mt-1 text-sm text-slate-500">Після каталогу: кошик, оформлення й order events.</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Tracking readiness</h2>
                    <div class="mt-5 flex items-end gap-3">
                        <div class="text-4xl font-bold text-[#454150]">0%</div>
                        <div class="pb-1 text-sm font-semibold text-slate-500">подій підключено</div>
                    </div>
                    <p class="mt-4 text-sm text-slate-500">Потрібно додати GA4/GTM/Meta/TikTok events після реалізації storefront і checkout.</p>
                </article>

                <article class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-bold text-[#343241]">Швидкий статус</h2>
                    <div class="mt-4 space-y-3 text-sm font-semibold">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">База e-commerce</span>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-600">готово</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Меню storefront</span>
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-600">готово</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Admin CRUD</span>
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-600">наступне</span>
                        </div>
                    </div>
                </article>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
