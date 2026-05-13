<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3,
    CircleDollarSign,
    PackageCheck,
    ShoppingCart,
    TrendingUp,
    UsersRound,
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
        maximumFractionDigits: 0,
    }).format((cents || 0) / 100);

const number = (value) => new Intl.NumberFormat('uk-UA').format(value || 0);

const stats = computed(() => props.dashboard.stats);
const chart = computed(() => props.dashboard.chart);

const cards = computed(() => [
    {
        label: 'Замовлення',
        value: number(stats.value.orders_count),
        hint: props.dashboard.period.label,
        icon: ShoppingCart,
    },
    {
        label: 'Дохід',
        value: money(stats.value.revenue_cents),
        hint: 'Сума замовлень за період',
        icon: CircleDollarSign,
    },
    {
        label: 'Середній чек',
        value: money(stats.value.average_order_cents),
        hint: `${number(stats.value.sold_units)} проданих одиниць`,
        icon: BarChart3,
    },
    {
        label: 'Клієнти',
        value: number(stats.value.unique_customers),
        hint: `${number(stats.value.customers_total)} у CRM`,
        icon: UsersRound,
    },
]);

const chartWidth = 920;
const chartHeight = 260;
const chartPadding = { top: 22, right: 24, bottom: 34, left: 44 };

const maxRevenue = computed(() => Math.max(...chart.value.revenue, 1));
const maxOrders = computed(() => Math.max(...chart.value.orders, 1));

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

const revenuePoints = computed(() => points(chart.value.revenue, maxRevenue.value));
const orderPoints = computed(() => points(chart.value.orders, maxOrders.value));
const revenueLine = computed(() => pathFromPoints(revenuePoints.value));
const orderLine = computed(() => pathFromPoints(orderPoints.value));
const gridLines = computed(() => [0, 1, 2, 3].map((step) => {
    const usableHeight = chartHeight - chartPadding.top - chartPadding.bottom;

    return chartPadding.top + (usableHeight / 3) * step;
}));

const sourceTotal = computed(() =>
    props.dashboard.sources.reduce((sum, source) => sum + source.orders, 0),
);

const sourcePercent = (orders) => {
    if (sourceTotal.value === 0) {
        return 0;
    }

    return Math.round((orders / sourceTotal.value) * 100);
};
</script>

<template>
    <Head title="Адмінка" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tight text-[#24212f]">
                        Панель керування
                    </h1>
                    <p class="mt-2 text-sm font-semibold text-slate-500">
                        Короткий стан магазину за останні 30 днів.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.orders.index')" class="rounded-lg bg-[#29277f] px-4 py-2.5 text-sm font-extrabold text-white shadow-sm transition hover:bg-[#232169]">
                        Замовлення
                    </Link>
                    <Link :href="route('admin.products.index')" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-extrabold text-[#343241] transition hover:border-[#29277f]/40">
                        Товари
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in cards"
                    :key="card.label"
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-slate-500">{{ card.label }}</p>
                            <p class="mt-2 text-2xl font-black tracking-tight text-[#24212f]">{{ card.value }}</p>
                        </div>
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#f2f0ff] text-[#29277f]">
                            <component :is="card.icon" class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="mt-4 text-xs font-semibold leading-5 text-slate-500">{{ card.hint }}</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1fr_340px]">
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-black text-[#24212f]">Динаміка продажів</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ dashboard.period.label }}</p>
                        </div>
                        <div class="flex gap-4 text-xs font-extrabold text-slate-500">
                            <span class="inline-flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-[#29277f]"></i>Дохід</span>
                            <span class="inline-flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-[#16a34a]"></i>Замовлення</span>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="min-w-[720px]">
                            <line
                                v-for="line in gridLines"
                                :key="line"
                                :x1="chartPadding.left"
                                :x2="chartWidth - chartPadding.right"
                                :y1="line"
                                :y2="line"
                                stroke="#e5e7eb"
                                stroke-width="1"
                            />
                            <path :d="revenueLine" fill="none" stroke="#29277f" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" />
                            <path :d="orderLine" fill="none" stroke="#16a34a" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
                            <text
                                v-for="(label, index) in chart.labels"
                                :key="label"
                                v-show="index % 4 === 0"
                                :x="chartPadding.left + ((chartWidth - chartPadding.left - chartPadding.right) / Math.max(chart.labels.length - 1, 1)) * index"
                                :y="chartHeight - 8"
                                fill="#94a3b8"
                                font-size="12"
                                font-weight="700"
                                text-anchor="middle"
                            >
                                {{ label }}
                            </text>
                        </svg>
                    </div>
                </article>

                <aside class="space-y-6">
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-black text-[#24212f]">Джерела</h2>
                                <p class="mt-1 text-sm font-semibold text-slate-500">UTM / source у замовленнях</p>
                            </div>
                            <TrendingUp class="h-5 w-5 text-[#29277f]" />
                        </div>

                        <div class="mt-5 space-y-4">
                            <div v-for="source in dashboard.sources" :key="source.label">
                                <div class="mb-1 flex items-center justify-between text-sm font-bold">
                                    <span class="text-[#343241]">{{ source.label }}</span>
                                    <span class="text-slate-500">{{ sourcePercent(source.orders) }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#29277f]" :style="{ width: `${sourcePercent(source.orders)}%` }"></div>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                    {{ number(source.orders) }} зам. · {{ money(source.revenue_cents) }}
                                </p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-black text-[#24212f]">Каталог</h2>
                        <div class="mt-4 flex items-center justify-between rounded-lg bg-slate-50 p-4">
                            <div>
                                <p class="text-sm font-bold text-slate-500">Активні товари</p>
                                <p class="mt-1 text-2xl font-black text-[#24212f]">{{ number(stats.active_products) }}</p>
                            </div>
                            <PackageCheck class="h-9 w-9 text-[#29277f]" />
                        </div>
                    </article>
                </aside>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
