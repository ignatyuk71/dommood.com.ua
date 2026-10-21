<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';

const props = defineProps({
    chart: {
        type: Object,
        required: true,
    },
    period: {
        type: Object,
        required: true,
    },
});

const chartWidth = 700;
const chartHeight = 220;
const chartTop = 20;
const chartFrame = ref(null);
const chartSvg = ref(null);
const selectedIndex = ref(null);
const tooltipStyle = ref({ left: '12px', width: '224px' });
const componentId = useId();
const tooltipId = `${componentId}-tooltip`;
const gradientId = `${componentId}-purchase-gradient`;
const series = computed(() => props.chart.series ?? []);
const labels = computed(() => props.chart.labels ?? []);
const maxValue = computed(() => Math.max(1, ...series.value.flatMap((item) => item.values ?? [0])));
const purchaseSeries = computed(() => series.value.find((item) => item.key === 'purchase'));
const numberFormatter = new Intl.NumberFormat('uk-UA');
const dateFormatter = new Intl.DateTimeFormat('uk-UA', { day: '2-digit', month: 'short', year: 'numeric' });

const pointX = (index) => labels.value.length > 1 ? (index / (labels.value.length - 1)) * chartWidth : 0;
const pointY = (value) => chartHeight - ((Number(value) || 0) / maxValue.value) * chartHeight;
const chartPoints = (values = []) => values.map((value, index) => `${pointX(index)},${pointY(value)}`).join(' ');
const chartAreaPoints = (values = []) => {
    const points = chartPoints(values);

    return points ? `0,${chartHeight} ${points} ${chartWidth},${chartHeight}` : '';
};
const axisLabels = computed(() => {
    const step = Math.max(1, Math.ceil(labels.value.length / 6));

    return labels.value
        .map((label, index) => ({ label, index, x: pointX(index) }))
        .filter(({ index }) => index % step === 0 || index === labels.value.length - 1);
});
const selection = computed(() => {
    const index = selectedIndex.value;

    if (index === null || index < 0 || index >= labels.value.length) {
        return null;
    }

    const hourly = props.chart.interval === 'hour';
    const [year, month, day] = props.period.start.split('-').map(Number);
    const date = new Date(year, month - 1, day + (hourly ? 0 : index));
    const dateLabel = dateFormatter.format(date);

    return {
        x: pointX(index),
        title: hourly ? `${dateLabel} · ${labels.value[index]}` : dateLabel,
        values: series.value.map((item) => ({ ...item, value: Number(item.values?.[index] ?? 0) || 0 })),
    };
});

const positionTooltip = () => {
    const matrix = chartSvg.value?.getScreenCTM();
    const bounds = chartFrame.value?.getBoundingClientRect();

    if (!selection.value || !matrix || !bounds) {
        return;
    }

    const anchor = chartSvg.value.createSVGPoint();
    anchor.x = selection.value.x;
    const x = anchor.matrixTransform(matrix).x - bounds.left;
    const width = Math.min(224, Math.max(0, bounds.width - 24));
    const left = x + width + 14 <= bounds.width - 12 ? x + 14 : x - width - 14;

    tooltipStyle.value = {
        left: `${Math.max(12, Math.min(left, bounds.width - width - 12))}px`,
        width: `${width}px`,
    };
};
const selectPoint = (index) => {
    selectedIndex.value = labels.value.length ? Math.max(0, Math.min(index, labels.value.length - 1)) : null;
    positionTooltip();
};
const clearSelection = () => {
    selectedIndex.value = null;
};
const selectFromPointer = (event) => {
    const svg = chartSvg.value;
    const matrix = svg?.getScreenCTM();

    if (!matrix || !labels.value.length) {
        clearSelection();
        return;
    }

    // Матриця SVG враховує масштаб і поля, коли пропорції графіка та контейнера різняться.
    const pointer = svg.createSVGPoint();
    pointer.x = event.clientX;
    pointer.y = event.clientY;
    const point = pointer.matrixTransform(matrix.inverse());

    if (point.x < 0 || point.x > chartWidth || point.y < chartTop || point.y > chartTop + chartHeight) {
        clearSelection();
        return;
    }

    selectPoint(Math.round((point.x / chartWidth) * (labels.value.length - 1)));
};
const leaveChart = (event) => {
    // Після дотику залишаємо підказку видимою для читання.
    if (event.pointerType !== 'touch') {
        clearSelection();
    }
};
const selectFromKeyboard = (event) => {
    if (!labels.value.length) {
        return;
    }

    const lastIndex = labels.value.length - 1;
    const targets = {
        ArrowLeft: (selectedIndex.value ?? labels.value.length) - 1,
        ArrowRight: (selectedIndex.value ?? -1) + 1,
        Home: 0,
        End: lastIndex,
    };

    if (event.key === 'Escape') {
        event.preventDefault();
        clearSelection();
    } else if (Object.hasOwn(targets, event.key)) {
        event.preventDefault();
        selectPoint(targets[event.key]);
    }
};

let resizeObserver;

onMounted(() => {
    resizeObserver = new ResizeObserver(positionTooltip);
    resizeObserver.observe(chartFrame.value);
});
onBeforeUnmount(() => resizeObserver?.disconnect());
watch([() => props.chart, () => props.period], clearSelection);
</script>

<template>
    <div ref="chartFrame" class="relative mt-5 overflow-hidden rounded-xl border border-slate-100 bg-gradient-to-b from-slate-50 to-white p-4">
        <svg
            ref="chartSvg"
            viewBox="0 0 700 260"
            class="h-72 w-full rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7561f7]"
            style="touch-action: pan-y pinch-zoom"
            tabindex="0"
            role="group"
            aria-label="Графік подій. Наведіть курсор, торкніться графіка або використовуйте стрілки вліво та вправо для вибору години чи дня."
            :aria-describedby="selection ? tooltipId : undefined"
            @pointermove="selectFromPointer"
            @pointerdown="selectFromPointer"
            @pointerleave="leaveChart"
            @pointercancel="clearSelection"
            @focus="selectPoint(selectedIndex ?? 0)"
            @blur="clearSelection"
            @keydown="selectFromKeyboard"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#16a34a" stop-opacity="0.20" />
                    <stop offset="100%" stop-color="#16a34a" stop-opacity="0" />
                </linearGradient>
            </defs>
            <g class="text-slate-200">
                <line v-for="line in [0, 1, 2, 3]" :key="line" x1="0" :x2="chartWidth" :y1="line * 73 + chartTop" :y2="line * 73 + chartTop" stroke="currentColor" stroke-dasharray="6 8" />
            </g>
            <g :transform="`translate(0 ${chartTop})`">
                <polygon
                    v-if="purchaseSeries"
                    :points="chartAreaPoints(purchaseSeries.values)"
                    :fill="`url(#${gradientId})`"
                />
                <polyline
                    v-for="item in series"
                    :key="item.key"
                    :points="chartPoints(item.values)"
                    fill="none"
                    :stroke="item.color"
                    stroke-width="4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <g v-if="selection" pointer-events="none">
                    <line
                        :x1="selection.x"
                        :x2="selection.x"
                        y1="0"
                        :y2="chartHeight"
                        stroke="#64748b"
                        stroke-width="1.5"
                        stroke-dasharray="5 5"
                    />
                    <circle
                        v-for="item in selection.values"
                        :key="item.key"
                        :cx="selection.x"
                        :cy="pointY(item.value)"
                        r="5"
                        :fill="item.color"
                        stroke="white"
                        stroke-width="2"
                    />
                </g>
            </g>
            <g class="text-[11px] font-bold text-slate-400">
                <text
                    v-for="label in axisLabels"
                    :key="label.index"
                    :x="label.x"
                    :text-anchor="label.index === 0 ? 'start' : label.index === labels.length - 1 ? 'end' : 'middle'"
                    y="255"
                    fill="currentColor"
                >
                    {{ label.label }}
                </text>
            </g>
            <rect x="0" :y="chartTop" :width="chartWidth" :height="chartHeight" fill="transparent" class="cursor-crosshair" />
        </svg>

        <div
            v-if="selection"
            :id="tooltipId"
            role="tooltip"
            class="pointer-events-none absolute top-3 z-10 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg"
            :style="tooltipStyle"
        >
            <p class="border-b border-slate-100 pb-2 text-xs font-extrabold text-[#343241]">{{ selection.title }}</p>
            <dl class="mt-2 space-y-2">
                <div v-for="item in selection.values" :key="item.key" class="flex items-center justify-between gap-3 text-xs">
                    <dt class="flex items-center gap-2 font-semibold text-slate-600">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: item.color }"></span>
                        {{ item.label }}
                    </dt>
                    <dd class="font-extrabold tabular-nums text-[#343241]">{{ numberFormatter.format(item.value) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
