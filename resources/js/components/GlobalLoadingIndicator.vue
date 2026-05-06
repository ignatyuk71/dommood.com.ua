<script setup>
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';

const isLoading = ref(false);
const progress = ref(0);

let progressTimer = null;
let hideTimer = null;

const stopProgressTimer = () => {
    if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
    }
};

const startLoading = () => {
    clearTimeout(hideTimer);
    stopProgressTimer();

    isLoading.value = true;
    progress.value = 12;
    progressTimer = setInterval(() => {
        progress.value = Math.min(progress.value + Math.max(2, (95 - progress.value) * 0.18), 95);
    }, 180);
};

const setUploadProgress = (event) => {
    const percentage = event.detail.progress?.percentage;

    if (Number.isFinite(percentage)) {
        progress.value = Math.max(progress.value, Math.min(percentage, 95));
    }
};

const finishLoading = () => {
    stopProgressTimer();
    progress.value = 100;

    hideTimer = setTimeout(() => {
        isLoading.value = false;
        progress.value = 0;
    }, 220);
};

const removeStartListener = router.on('start', startLoading);
const removeProgressListener = router.on('progress', setUploadProgress);
const removeFinishListener = router.on('finish', finishLoading);

onBeforeUnmount(() => {
    removeStartListener();
    removeProgressListener();
    removeFinishListener();
    stopProgressTimer();
    clearTimeout(hideTimer);
});
</script>

<template>
    <div
        v-show="isLoading"
        class="fixed inset-x-0 top-0 z-[70] h-1 overflow-hidden bg-[#7561f7]/10"
        aria-hidden="true"
    >
        <div
            class="h-full rounded-r-full bg-[#7561f7] shadow-[0_0_18px_rgba(117,97,247,0.45)] transition-[width] duration-150 ease-out"
            :style="{ width: `${progress}%` }"
        />
    </div>

    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-2 opacity-0"
    >
        <div
            v-if="isLoading"
            class="fixed right-4 top-4 z-[60] inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-[#343241] shadow-[0_18px_45px_rgba(61,58,101,0.18)] md:right-6"
            role="status"
            aria-live="polite"
        >
            <LoaderCircle class="h-4 w-4 animate-spin text-[#7561f7]" />
            Завантаження
        </div>
    </Transition>
</template>
