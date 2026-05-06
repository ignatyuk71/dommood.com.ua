<script setup>
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';

const isLoading = ref(false);

let hideTimer = null;
let startedAt = 0;

const startLoading = () => {
    clearTimeout(hideTimer);
    startedAt = Date.now();
    isLoading.value = true;
};

const finishLoading = () => {
    const visibleMs = Date.now() - startedAt;
    const hideDelay = Math.max(180 - visibleMs, 0);

    hideTimer = setTimeout(() => {
        isLoading.value = false;
    }, hideDelay);
};

const removeStartListener = router.on('start', startLoading);
const removeFinishListener = router.on('finish', finishLoading);

onBeforeUnmount(() => {
    removeStartListener();
    removeFinishListener();
    clearTimeout(hideTimer);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="isLoading"
            class="absolute inset-0 z-30 flex items-center justify-center rounded-lg bg-slate-100/70 backdrop-blur-[2px]"
            role="status"
            aria-live="polite"
            aria-label="Виконується дія"
        >
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-full border border-white/80 bg-white/90 text-[#7561f7] shadow-[0_18px_45px_rgba(61,58,101,0.2)]">
                <LoaderCircle class="h-8 w-8 animate-spin" />
            </div>
        </div>
    </Transition>
</template>
