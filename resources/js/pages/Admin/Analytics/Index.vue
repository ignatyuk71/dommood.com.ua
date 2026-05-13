<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    BadgeCheck,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Circle,
    DatabaseZap,
    LineChart,
    MousePointerClick,
    PlugZap,
    ServerCog,
    Settings2,
    ShieldAlert,
    ShoppingCart,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    activeChannel: {
        type: String,
        required: true,
    },
    channels: {
        type: Array,
        required: true,
    },
    integration: {
        type: Object,
        required: true,
    },
    trackingPlan: {
        type: Array,
        required: true,
    },
    analytics: {
        type: Object,
        required: true,
    },
});

const datePickerOpen = ref(false);
const active = computed(() => props.channels.find((channel) => channel.key === props.activeChannel) ?? props.channels[0]);

const cards = computed(() => [
    {
        label: 'Статус інтеграції',
        value: props.integration.status_label,
        detail: props.integration.configured ? 'Налаштування збережені' : 'Потрібно заповнити доступи',
        icon: PlugZap,
    },
    {
        label: 'Browser tracking',
        value: props.integration.browser_ready ? 'Активний' : 'Не готовий',
        detail: 'Pixel / GTM події на storefront',
        icon: DatabaseZap,
    },
    {
        label: 'Server API',
        value: props.integration.server_ready ? 'Активний' : 'Не готовий',
        detail: 'CAPI / Events API / Ads conversions',
        icon: ShoppingCart,
    },
]);

const statusClass = computed(() => {
    if (props.integration.server_ready && props.integration.browser_ready) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (props.integration.configured) {
        return 'bg-blue-50 text-blue-700';
    }

    return 'bg-amber-50 text-amber-700';
});

const chartSeries = computed(() => props.analytics.chart?.series ?? []);
const chartLabels = computed(() => props.analytics.chart?.labels ?? []);
const chartMax = computed(() => Math.max(1, ...chartSeries.value.flatMap((series) => series.values ?? [0])));
const purchaseSeries = computed(() => chartSeries.value.find((series) => series.key === 'purchase'));
const eventLog = computed(() => props.analytics.eventLog ?? []);
const selectedStart = ref(props.analytics.period.start);
const selectedEnd = ref(props.analytics.period.end);

const formatNumber = (value) => new Intl.NumberFormat('uk-UA').format(Number(value ?? 0));
const formatMoney = (cents) => `${new Intl.NumberFormat('uk-UA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(cents ?? 0) / 100)} грн`;
const metricValue = (metric) => metric.format === 'money_cents' ? formatMoney(metric.value) : formatNumber(metric.value);
const monthNames = [
    'Січень',
    'Лютий',
    'Березень',
    'Квітень',
    'Травень',
    'Червень',
    'Липень',
    'Серпень',
    'Вересень',
    'Жовтень',
    'Листопад',
    'Грудень',
];
const weekDays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд'];
const toIsoDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const parseIsoDate = (value) => {
    const [year, month, day] = String(value).split('-').map(Number);

    return new Date(year, month - 1, day);
};
const addDays = (date, days) => {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
};
const addMonths = (date, months) => {
    const next = new Date(date);
    next.setMonth(next.getMonth() + months);

    return next;
};
const startOfMonth = (date) => new Date(date.getFullYear(), date.getMonth(), 1);
const formatShortDate = (value) => new Intl.DateTimeFormat('uk-UA', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
}).format(parseIsoDate(value));
const calendarCursor = ref(startOfMonth(parseIsoDate(selectedStart.value)));

const buildMonth = (date) => {
    const monthStart = startOfMonth(date);
    const monthIndex = monthStart.getMonth();
    const firstWeekOffset = (monthStart.getDay() + 6) % 7;
    const gridStart = addDays(monthStart, -firstWeekOffset);
    const days = Array.from({ length: 42 }, (_, index) => {
        const day = addDays(gridStart, index);
        const iso = toIsoDate(day);

        return {
            iso,
            day: day.getDate(),
            inMonth: day.getMonth() === monthIndex,
        };
    });

    return {
        title: `${monthNames[monthIndex]} ${monthStart.getFullYear()}`,
        weeks: Array.from({ length: 6 }, (_, index) => days.slice(index * 7, index * 7 + 7)),
    };
};
const calendarMonths = computed(() => [
    buildMonth(calendarCursor.value),
    buildMonth(addMonths(calendarCursor.value, 1)),
]);
const presetRanges = computed(() => {
    const today = new Date();
    const period = (days) => ({
        start: toIsoDate(addDays(today, -(days - 1))),
        end: toIsoDate(today),
    });

    return [
        { label: 'Сьогодні', ...period(1) },
        { label: '3 дні', ...period(3) },
        { label: 'Тиждень', ...period(7) },
        { label: 'Місяць', ...period(30) },
        { label: '2 місяці', ...period(60) },
        { label: 'Рік', ...period(365) },
    ];
});
const selectedRangeLabel = computed(() => {
    if (!selectedStart.value || !selectedEnd.value) {
        return 'Вибери період';
    }

    return `${formatShortDate(selectedStart.value)} — ${formatShortDate(selectedEnd.value)}`;
});
const chartAxisLabels = computed(() => {
    const labels = chartLabels.value;
    const step = Math.max(1, Math.ceil(labels.length / 6));

    return labels
        .map((label, index) => ({
            label,
            x: labels.length > 1 ? (index / (labels.length - 1)) * 700 : 0,
            visible: index % step === 0 || index === labels.length - 1,
        }))
        .filter((label) => label.visible);
});

const chartPoints = (values = []) => {
    if (!values.length) {
        return '';
    }

    const width = 700;
    const height = 220;
    const step = values.length > 1 ? width / (values.length - 1) : width;

    return values
        .map((value, index) => {
            const x = index * step;
            const y = height - ((Number(value) || 0) / chartMax.value) * height;

            return `${x},${y}`;
        })
        .join(' ');
};
const chartAreaPoints = (values = []) => {
    const points = chartPoints(values);

    return points ? `0,220 ${points} 700,220` : '';
};
const isCalendarSelected = (iso) => iso === selectedStart.value || iso === selectedEnd.value;
const isCalendarBetween = (iso) => selectedStart.value
    && selectedEnd.value
    && iso > selectedStart.value
    && iso < selectedEnd.value;
const selectCalendarDay = (iso) => {
    if (!selectedStart.value || selectedEnd.value) {
        selectedStart.value = iso;
        selectedEnd.value = '';
        return;
    }

    if (iso < selectedStart.value) {
        selectedEnd.value = selectedStart.value;
        selectedStart.value = iso;
        return;
    }

    selectedEnd.value = iso;
};
const applyPreset = (preset) => {
    selectedStart.value = preset.start;
    selectedEnd.value = preset.end;
    calendarCursor.value = startOfMonth(parseIsoDate(preset.start));
    applyDateRange();
};
const applyDateRange = () => {
    const startDate = selectedStart.value;
    const endDate = selectedEnd.value || selectedStart.value;

    router.get(route('admin.analytics.show', props.activeChannel), {
        start_date: startDate,
        end_date: endDate,
    }, {
        preserveScroll: true,
        preserveState: false,
    });
    datePickerOpen.value = false;
};
const resetDateRange = () => {
    const preset = presetRanges.value.find((item) => item.label === 'Місяць') ?? presetRanges.value[0];
    applyPreset(preset);
};

const statusBadgeClass = (status) => ({
    sent: 'bg-emerald-50 text-emerald-700',
    recorded: 'bg-blue-50 text-blue-700',
    queued: 'bg-slate-100 text-slate-600',
    processing: 'bg-indigo-50 text-indigo-700',
    failed: 'bg-red-50 text-red-700',
}[status] ?? 'bg-slate-100 text-slate-600');
const eventDatePart = (value) => String(value ?? '—').split(' ')[0] ?? '—';
const eventTimePart = (value) => String(value ?? '').split(' ')[1] ?? '';

watch(() => props.analytics.period, (period) => {
    selectedStart.value = period.start;
    selectedEnd.value = period.end;
    calendarCursor.value = startOfMonth(parseIsoDate(period.start));
});
</script>

<template>
    <Head :title="`Аналітика: ${active.label}`" />

    <AuthenticatedLayout>
        <section class="mb-5 flex flex-col gap-4 rounded-lg bg-white px-5 py-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)] lg:flex-row lg:items-center lg:justify-between lg:px-7">
            <div>
                <p class="text-sm font-extrabold text-slate-500">Маркетинг і tracking</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-[#343241]">Аналітика</h1>
                <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-500">
                    Окремий центр для Google, TikTok і Meta. Тут буде контроль подій, атрибуції, витрат і ROAS.
                </p>
            </div>

            <div class="flex w-full flex-col gap-3 lg:w-auto lg:flex-row lg:items-center lg:justify-end">
                <div class="inline-flex w-full rounded-xl bg-slate-100 p-1 lg:w-auto">
                    <Link
                        v-for="channel in channels"
                        :key="channel.key"
                        :href="channel.route"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-extrabold transition lg:flex-none"
                        :class="channel.key === activeChannel ? 'bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20' : 'text-slate-600 hover:bg-white'"
                    >
                        <Circle class="h-3 w-3 fill-current" :style="{ color: channel.key === activeChannel ? '#fff' : channel.color }" />
                        {{ channel.label }}
                    </Link>
                </div>

                <div class="relative w-full lg:w-auto">
                    <button
                        type="button"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-extrabold text-[#343241] shadow-sm transition hover:border-[#7561f7] lg:w-auto"
                        @click="datePickerOpen = !datePickerOpen"
                    >
                        <CalendarDays class="h-4 w-4 text-[#7561f7]" />
                        {{ selectedRangeLabel }}
                    </button>

                    <div
                        v-if="datePickerOpen"
                        class="absolute right-0 z-40 mt-3 w-[min(92vw,760px)] overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-[0_24px_80px_rgba(61,58,101,0.18)]"
                    >
                        <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_180px]">
                            <div class="p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                                        @click="calendarCursor = startOfMonth(addMonths(calendarCursor, -1))"
                                    >
                                        <ChevronLeft class="h-5 w-5" />
                                    </button>
                                    <p class="text-sm font-black text-[#343241]">Вибір періоду</p>
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                                        @click="calendarCursor = startOfMonth(addMonths(calendarCursor, 1))"
                                    >
                                        <ChevronRight class="h-5 w-5" />
                                    </button>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <section v-for="month in calendarMonths" :key="month.title">
                                        <h3 class="mb-3 text-center text-sm font-black text-[#343241]">{{ month.title }}</h3>
                                        <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-black uppercase text-slate-400">
                                            <span v-for="day in weekDays" :key="day">{{ day }}</span>
                                        </div>
                                        <div class="mt-2 space-y-1">
                                            <div v-for="(week, weekIndex) in month.weeks" :key="weekIndex" class="grid grid-cols-7 gap-1">
                                                <button
                                                    v-for="day in week"
                                                    :key="day.iso"
                                                    type="button"
                                                    class="h-9 rounded-lg text-sm font-extrabold transition"
                                                    :class="[
                                                        day.inMonth ? 'text-slate-700' : 'text-slate-300',
                                                        isCalendarSelected(day.iso) ? 'bg-[#7561f7] text-white shadow-md shadow-indigo-500/20' : '',
                                                        isCalendarBetween(day.iso) ? 'bg-indigo-50 text-[#7561f7]' : '',
                                                        !isCalendarSelected(day.iso) && !isCalendarBetween(day.iso) ? 'hover:bg-slate-100' : '',
                                                    ]"
                                                    @click="selectCalendarDay(day.iso)"
                                                >
                                                    {{ day.day }}
                                                </button>
                                            </div>
                                        </div>
                                    </section>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                    <p class="text-xs font-extrabold text-slate-500">
                                        {{ selectedRangeLabel }}
                                    </p>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="h-10 rounded-xl px-4 text-sm font-extrabold text-slate-500 hover:bg-slate-100"
                                            @click="resetDateRange"
                                        >
                                            30 днів
                                        </button>
                                        <button
                                            type="button"
                                            class="h-10 rounded-xl bg-[#343241] px-5 text-sm font-extrabold text-white hover:bg-[#24212f]"
                                            @click="applyDateRange"
                                        >
                                            Застосувати
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <aside class="border-t border-slate-100 bg-slate-50 p-4 lg:border-l lg:border-t-0">
                                <p class="mb-3 text-xs font-black uppercase tracking-wide text-slate-500">Швидко</p>
                                <div class="grid gap-2">
                                    <button
                                        v-for="preset in presetRanges"
                                        :key="preset.label"
                                        type="button"
                                        class="h-10 rounded-xl px-3 text-left text-sm font-extrabold text-slate-600 transition hover:bg-white hover:text-[#7561f7]"
                                        @click="applyPreset(preset)"
                                    >
                                        {{ preset.label }}
                                    </button>
                                </div>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <div class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-4">
                            <div
                                class="inline-flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-lg"
                                :style="{ backgroundColor: active.color }"
                            >
                                <BarChart3 class="h-7 w-7" />
                            </div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-2xl font-black text-[#343241]">{{ active.label }}</h2>
                                    <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-600">{{ active.badge }}</span>
                                </div>
                                <p class="mt-1 max-w-3xl text-sm font-semibold text-slate-500">{{ active.description }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-extrabold" :class="statusClass">
                                <Activity class="h-4 w-4" />
                                {{ integration.status_label }}
                            </span>
                            <Link
                                :href="route('admin.settings.tracking.show', activeChannel)"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#343241] px-4 text-sm font-extrabold text-white hover:bg-[#24212f]"
                            >
                                <Settings2 class="h-4 w-4" />
                                Налаштувати
                            </Link>
                        </div>
                    </div>

                    <div class="grid gap-4 p-5 lg:grid-cols-3">
                        <article
                            v-for="card in cards"
                            :key="card.label"
                            class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                        >
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[#7561f7] shadow-sm">
                                    <component :is="card.icon" class="h-5 w-5" />
                                </span>
                                <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                            </div>
                            <p class="mt-1 text-lg font-black text-[#343241]">{{ card.value }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ card.detail }}</p>
                        </article>
                    </div>
                </div>

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <LineChart class="h-5 w-5 text-[#7561f7]" />
                            <div>
                                <h2 class="text-lg font-black text-[#343241]">Динаміка подій</h2>
                                <p class="text-xs font-bold text-slate-500">{{ analytics.period.label }} · {{ analytics.period.start }} — {{ analytics.period.end }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="series in chartSeries"
                                :key="series.key"
                                class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-1.5 text-xs font-extrabold text-slate-600"
                            >
                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: series.color }"></span>
                                {{ series.label }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-xl border border-slate-100 bg-gradient-to-b from-slate-50 to-white p-4">
                        <svg viewBox="0 0 700 260" class="h-72 w-full">
                            <defs>
                                <linearGradient id="purchaseGradient" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#16a34a" stop-opacity="0.20" />
                                    <stop offset="100%" stop-color="#16a34a" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <g class="text-slate-200">
                                <line v-for="line in [0, 1, 2, 3]" :key="line" x1="0" x2="700" :y1="line * 73 + 20" :y2="line * 73 + 20" stroke="currentColor" stroke-dasharray="6 8" />
                            </g>
                            <g transform="translate(0 20)">
                                <polygon
                                    v-if="purchaseSeries"
                                    :points="chartAreaPoints(purchaseSeries.values)"
                                    fill="url(#purchaseGradient)"
                                />
                                <polyline
                                    v-for="series in chartSeries"
                                    :key="series.key"
                                    :points="chartPoints(series.values)"
                                    fill="none"
                                    :stroke="series.color"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </g>
                            <g class="text-[11px] font-bold text-slate-400">
                                <text
                                    v-for="label in chartAxisLabels"
                                    :key="`${label.label}-${label.x}`"
                                    :x="Math.min(675, label.x)"
                                    y="255"
                                    fill="currentColor"
                                >
                                    {{ label.label }}
                                </text>
                            </g>
                        </svg>
                    </div>
                </section>

                <div class="grid gap-5 lg:grid-cols-2">
                    <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="flex items-center gap-3">
                            <LineChart class="h-5 w-5 text-[#7561f7]" />
                            <div>
                                <h2 class="text-lg font-black text-[#343241]">Метрики</h2>
                                <p class="text-xs font-bold text-slate-500">Короткий підсумок з локальної бази подій.</p>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3">
                            <div
                                v-for="metric in analytics.metrics"
                                :key="metric.label"
                                class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3"
                            >
                                <span>
                                    <span class="block text-sm font-extrabold text-slate-700">{{ metric.label }}</span>
                                    <span class="block text-xs font-semibold text-slate-500">{{ metric.hint }}</span>
                                </span>
                                <span class="shrink-0 text-lg font-black text-[#343241]">{{ metricValue(metric) }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="flex items-center gap-3">
                            <MousePointerClick class="h-5 w-5 text-[#7561f7]" />
                            <div>
                                <h2 class="text-lg font-black text-[#343241]">Події funnel</h2>
                                <p class="text-xs font-bold text-slate-500">Воронка від перегляду товару до покупки.</p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div v-for="step in analytics.funnel" :key="step.key">
                                <div class="mb-1 flex items-center justify-between gap-3">
                                    <span class="text-sm font-extrabold text-slate-700">{{ step.label }}</span>
                                    <span class="text-sm font-black text-[#343241]">{{ formatNumber(step.count) }}</span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full bg-[#7561f7]"
                                        :style="{ width: `${step.rate}%` }"
                                    ></div>
                                </div>
                                <p class="mt-1 text-xs font-bold text-slate-400">{{ step.rate }}% від переглядів товару</p>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <Activity class="h-5 w-5 text-[#7561f7]" />
                            <div>
                                <h2 class="text-lg font-black text-[#343241]">Журнал подій</h2>
                                <p class="text-xs font-bold text-slate-500">Останні browser-записи та server-side відправки.</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-extrabold text-slate-500">
                            {{ eventLog.length }} записів
                        </span>
                    </div>

                    <div v-if="eventLog.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left">
                            <thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Дата</th>
                                    <th class="px-5 py-3">Подія</th>
                                    <th class="px-5 py-3">Event ID</th>
                                    <th class="px-5 py-3">Канал</th>
                                    <th class="px-5 py-3">Статус</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="event in eventLog" :key="event.id" class="text-sm">
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <span class="block font-extrabold text-slate-600">{{ eventDatePart(event.date) }}</span>
                                        <span class="mt-0.5 block text-xs font-bold text-slate-400">{{ eventTimePart(event.date) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 font-extrabold text-[#343241]">{{ event.event_name }}</td>
                                    <td class="max-w-xs px-5 py-3">
                                        <span class="inline-flex max-w-xs truncate rounded-lg bg-red-50 px-2.5 py-1 font-mono text-xs font-black text-red-600 ring-1 ring-red-100">
                                            {{ event.event_id }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 font-bold text-slate-600">{{ event.transport }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold" :class="statusBadgeClass(event.status)">
                                            {{ event.status }}
                                        </span>
                                        <p v-if="event.message" class="mt-1 max-w-xs truncate text-xs font-semibold text-slate-400">{{ event.message }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-5 py-12 text-center">
                        <Activity class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3 text-sm font-extrabold text-[#343241]">Подій ще немає</p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Коли storefront почне писати події, вони з’являться тут.</p>
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <ShieldAlert class="h-5 w-5 text-[#7561f7]" />
                            <h2 class="text-lg font-black text-[#343241]">Підключення</h2>
                        </div>
                        <span class="h-3 w-3 rounded-full" :class="integration.configured ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                            <span class="text-sm font-bold text-slate-600">Browser</span>
                            <span class="inline-flex items-center gap-2 text-sm font-extrabold" :class="integration.browser_ready ? 'text-emerald-600' : 'text-slate-400'">
                                <CheckCircle2 class="h-4 w-4" />
                                {{ integration.browser_ready ? 'Готово' : 'Не готово' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                            <span class="text-sm font-bold text-slate-600">Server API</span>
                            <span class="inline-flex items-center gap-2 text-sm font-extrabold" :class="integration.server_ready ? 'text-emerald-600' : 'text-slate-400'">
                                <CheckCircle2 class="h-4 w-4" />
                                {{ integration.server_ready ? 'Готово' : 'Не готово' }}
                            </span>
                        </div>
                        <p v-if="integration.last_error" class="rounded-xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                            {{ integration.last_error }}
                        </p>
                    </div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex items-center gap-3">
                        <ServerCog class="h-5 w-5 text-[#7561f7]" />
                        <h2 class="text-lg font-black text-[#343241]">Як підключаємо</h2>
                    </div>
                    <ol class="mt-4 space-y-3">
                        <li
                            v-for="(step, index) in trackingPlan"
                            :key="step"
                            class="flex gap-3 rounded-xl bg-slate-50 px-3 py-3"
                        >
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-xs font-black text-[#7561f7]">
                                {{ index + 1 }}
                            </span>
                            <span class="text-sm font-semibold leading-6 text-slate-600">{{ step }}</span>
                        </li>
                    </ol>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex items-start gap-3">
                        <BadgeCheck class="mt-0.5 h-5 w-5 text-emerald-500" />
                        <div>
                            <h2 class="text-lg font-black text-[#343241]">Наступний етап</h2>
                            <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">
                                {{ integration.next_step }}
                            </p>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

    </AuthenticatedLayout>
</template>
