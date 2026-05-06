<script setup>
import { router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Info,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const page = usePage();
const toasts = ref([]);
const timers = new Map();
const recentToastKeys = new Map();
let nextId = 1;
let lastRouterPayloadKey = null;
let lastRouterPayloadAt = 0;
const duplicateWindowMs = 1500;

const config = {
    success: {
        icon: CheckCircle2,
        title: 'Успішно',
        class: 'border-emerald-100 bg-emerald-50 text-emerald-800',
        iconClass: 'bg-emerald-100 text-emerald-700',
    },
    error: {
        icon: AlertCircle,
        title: 'Помилка',
        class: 'border-red-100 bg-red-50 text-red-800',
        iconClass: 'bg-red-100 text-red-700',
    },
    warning: {
        icon: TriangleAlert,
        title: 'Увага',
        class: 'border-amber-100 bg-amber-50 text-amber-800',
        iconClass: 'bg-amber-100 text-amber-700',
    },
    info: {
        icon: Info,
        title: 'Інформація',
        class: 'border-sky-100 bg-sky-50 text-sky-800',
        iconClass: 'bg-sky-100 text-sky-700',
    },
};

const visibleToasts = computed(() => toasts.value.slice(-4));
const flashPayload = computed(() => ({
    url: page.url,
    success: page.props.flash?.success ?? null,
    error: page.props.flash?.error ?? null,
    warning: page.props.flash?.warning ?? null,
    info: page.props.flash?.info ?? null,
    categoryError: page.props.errors?.category ?? null,
}));

let lastPayloadKey = null;

const payloadKey = (props, url) => JSON.stringify({
    url,
    success: props.flash?.success ?? null,
    error: props.flash?.error ?? null,
    warning: props.flash?.warning ?? null,
    info: props.flash?.info ?? null,
    categoryError: props.errors?.category ?? null,
});

const hasToastPayload = (props) => Boolean(
    props.flash?.success
    || props.flash?.error
    || props.flash?.warning
    || props.flash?.info
    || typeof props.errors?.category === 'string',
);

const dismissToast = (id) => {
    clearTimeout(timers.get(id));
    timers.delete(id);
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
};

const toastKey = (type, message) => `${type}:${message}`;

const addToast = (type, message) => {
    if (!message) {
        return;
    }

    const key = toastKey(type, message);
    const lastShownAt = recentToastKeys.get(key) ?? 0;

    if (Date.now() - lastShownAt < duplicateWindowMs) {
        return;
    }

    recentToastKeys.set(key, Date.now());

    const id = nextId++;
    toasts.value = [
        ...toasts.value,
        {
            id,
            type,
            message,
            ...config[type],
        },
    ];

    timers.set(id, setTimeout(() => dismissToast(id), type === 'error' ? 7000 : 4500));
};

const pushFromProps = (props) => {
    const flash = props.flash ?? {};

    addToast('success', flash.success);
    addToast('error', flash.error);
    addToast('warning', flash.warning);
    addToast('info', flash.info);

    if (typeof props.errors?.category === 'string') {
        addToast('error', props.errors.category);
    }
};

const pushCurrentPageToasts = () => {
    if (!hasToastPayload(page.props)) {
        return;
    }

    const currentPayloadKey = JSON.stringify(flashPayload.value);

    if (currentPayloadKey === lastPayloadKey) {
        return;
    }

    if (
        currentPayloadKey === lastRouterPayloadKey
        && Date.now() - lastRouterPayloadAt < 500
    ) {
        lastPayloadKey = currentPayloadKey;
        return;
    }

    lastPayloadKey = currentPayloadKey;
    pushFromProps(page.props);
};

watch(flashPayload, pushCurrentPageToasts, { immediate: true });

const removeSuccessListener = router.on('success', (event) => {
    const props = event.detail.page.props ?? {};

    if (!hasToastPayload(props)) {
        return;
    }

    lastRouterPayloadKey = payloadKey(props, event.detail.page.url);
    lastRouterPayloadAt = Date.now();
    pushFromProps(props);
});

const removeErrorListener = router.on('error', (errors) => {
    if (typeof errors.detail.errors?.category === 'string') {
        addToast('error', errors.detail.errors.category);
    }
});

onBeforeUnmount(() => {
    removeSuccessListener();
    removeErrorListener();
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
    recentToastKeys.clear();
});
</script>

<template>
    <div class="fixed right-4 top-20 z-[65] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 md:right-6">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-4 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-4 opacity-0"
        >
            <div
                v-for="toast in visibleToasts"
                :key="toast.id"
                class="flex gap-3 rounded-lg border p-4 shadow-[0_18px_45px_rgba(61,58,101,0.18)] backdrop-blur"
                :class="toast.class"
                role="status"
                aria-live="polite"
            >
                <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="toast.iconClass">
                    <component :is="toast.icon" class="h-5 w-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold">{{ toast.title }}</div>
                    <div class="mt-1 break-words text-sm font-semibold leading-5">
                        {{ toast.message }}
                    </div>
                </div>

                <button
                    type="button"
                    class="-mr-1 -mt-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg opacity-70 transition hover:bg-white/70 hover:opacity-100"
                    aria-label="Закрити повідомлення"
                    @click="dismissToast(toast.id)"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
