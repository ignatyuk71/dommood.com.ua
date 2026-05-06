<script setup>
import { router } from '@inertiajs/vue3';
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
</template>
